<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Actions\ActivateMember;
use App\Domain\Membership\Actions\CreateMember;
use App\Domain\Membership\Actions\ExitMember;
use App\Domain\Membership\Actions\LinkMemberToUser;
use App\Domain\Membership\Actions\ReinstateMember;
use App\Domain\Membership\Actions\ReviewMemberDocument;
use App\Domain\Membership\Actions\SuspendMember;
use App\Domain\Membership\Actions\UnlinkMemberFromUser;
use App\Domain\Membership\Actions\UpdateMemberProfile;
use App\Domain\Membership\Actions\UploadMemberDocument;
use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Enums\MemberDocumentType;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Membership\Services\MembershipNumberGenerator;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function establishMembershipDomainContext(
    User $actor,
    Organisation $organisation,
    ?Branch $branch = null,
): void {
    OrganisationUser::factory()->for($actor)->for($organisation)->create();
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName(SystemRole::OrganisationAdmin->value)->getKey(),
        ])->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment);
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('membership numbers use an organisation year locked sequence without reuse', function () {
    $firstOrganisation = Organisation::factory()->create(['code' => 'UNITY']);
    $secondOrganisation = Organisation::factory()->create(['code' => 'HOPE']);
    $generator = app(MembershipNumberGenerator::class);

    expect($generator->next($firstOrganisation, 2026))->toBe('UNITY-2026-000001')
        ->and($generator->next($firstOrganisation, 2026))->toBe('UNITY-2026-000002')
        ->and($generator->next($secondOrganisation, 2026))->toBe('HOPE-2026-000001');
});

test('creating a member derives ownership normalizes phone and records initial history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    establishMembershipDomainContext($actor, $organisation);

    $member = app(CreateMember::class)->handle($actor, [
        'first_name' => 'Ama',
        'last_name' => 'Mensah',
        'phone' => '024 123 4567',
        'status' => MemberStatus::Pending->value,
    ]);

    expect($member->organisation_id)->toBe($organisation->id)
        ->and($member->user_id)->toBeNull()
        ->and($member->public_id)->not->toBeEmpty()
        ->and($member->membership_number)->toBe('UNITY-2026-000001')
        ->and($member->phone)->toBe('+233241234567')
        ->and($member->statusHistories()->count())->toBe(1)
        ->and($member->statusHistories()->first()->to_status)->toBe(MemberStatus::Pending);
});

test('branch ownership and branch role scope are enforced', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for(Organisation::factory())->create();
    establishMembershipDomainContext($actor, $organisation, $branch);

    app(CreateMember::class)->handle($actor, [
        'first_name' => 'Kojo',
        'last_name' => 'Asare',
        'phone' => '0200000000',
    ], $otherBranch);
})->throws(InvalidMembershipOperation::class);

test('member profile updates normalize editable data and protect system fields', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->for($branch)->create([
        'membership_number' => 'ORIGINAL-NUMBER',
        'status' => MemberStatus::Active,
        'user_id' => null,
    ]);

    $updated = app(UpdateMemberProfile::class)->handle($member, [
        'first_name' => 'Updated',
        'phone' => '024 123 4567',
        'membership_number' => 'TAMPERED',
        'organisation_id' => Organisation::factory()->create()->id,
        'status' => MemberStatus::Exited->value,
        'user_id' => User::factory()->create()->id,
        'joined_at' => now()->addYear()->toDateString(),
    ], $branch);

    expect($updated->first_name)->toBe('Updated')
        ->and($updated->phone)->toBe('+233241234567')
        ->and($updated->organisation_id)->toBe($organisation->id)
        ->and($updated->branch_id)->toBe($branch->id)
        ->and($updated->membership_number)->toBe('ORIGINAL-NUMBER')
        ->and($updated->status)->toBe(MemberStatus::Active)
        ->and($updated->user_id)->toBeNull();
});

test('member profile updates reject a foreign organisation in organisation-wide context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $foreignMember = Member::factory()->for(Organisation::factory())->create();

    app(UpdateMemberProfile::class)->handle($foreignMember, [
        'first_name' => 'Tampered',
    ], null);
})->throws(InvalidMembershipOperation::class);

test('an explicitly selected branch context restricts an organisation wide role', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    establishMembershipDomainContext($actor, $organisation);

    $context = app(TenantContext::class);
    $context->setBranch($activeBranch);

    app(CreateMember::class)->handle($actor, [
        'first_name' => 'Kojo',
        'last_name' => 'Asare',
        'phone' => '0200000000',
    ], $otherBranch);
})->throws(InvalidMembershipOperation::class);

test('explicit lifecycle transitions preserve append only history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->pending()->for($organisation)->create();

    $member = app(ActivateMember::class)->handle($member, $actor);
    $member = app(SuspendMember::class)->handle($member, $actor, 'Compliance review');
    $member = app(ReinstateMember::class)->handle($member, $actor);
    $member = app(ExitMember::class)->handle($member, $actor, now(), 'Relocated');

    expect($member->status)->toBe(MemberStatus::Exited)
        ->and($member->suspension_reason)->toBeNull()
        ->and($member->statusHistories()->count())->toBe(4)
        ->and($member->exists)->toBeTrue();
});

test('invalid lifecycle transitions and suspension without a reason are rejected', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->pending()->for($organisation)->create();

    app(SuspendMember::class)->handle($member, $actor, '');
})->throws(InvalidMembershipOperation::class);

test('user links require organisation access and are unique among current memberships', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    $first = Member::factory()->for($organisation)->create();
    $second = Member::factory()->for($organisation)->create();
    app(LinkMemberToUser::class)->handle($first, $target);

    app(LinkMemberToUser::class)->handle($second, $target);
})->throws(InvalidMembershipOperation::class);

test('user link and unlink actions reject members outside the active organisation', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    OrganisationUser::factory()->for($target)->for($foreignOrganisation)->create();
    $foreignMember = Member::factory()
        ->for($foreignOrganisation)
        ->for($target)
        ->create([
            'active_user_link_key' => Member::activeUserLinkKey($foreignOrganisation, $target),
        ]);

    expect(fn () => app(LinkMemberToUser::class)->handle($foreignMember, $target))
        ->toThrow(InvalidMembershipOperation::class)
        ->and(fn () => app(UnlinkMemberFromUser::class)->handle($foreignMember))
        ->toThrow(InvalidMembershipOperation::class)
        ->and($foreignMember->refresh()->user_id)->toBe($target->id);
});

test('private document uploads reject foreign members and invalid document types', function () {
    Storage::fake('local');
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $foreignMember = Member::factory()->for(Organisation::factory())->create();
    $upload = app(UploadMemberDocument::class);

    expect(fn () => $upload->handle(
        $foreignMember,
        $actor,
        MemberDocumentType::NationalId->value,
        UploadedFile::fake()->create('foreign.pdf', 20, 'application/pdf'),
    ))->toThrow(InvalidMembershipOperation::class)
        ->and(fn () => $upload->handle(
            Member::factory()->for($organisation)->create(),
            $actor,
            'tampered_type',
            UploadedFile::fake()->create('invalid.pdf', 20, 'application/pdf'),
        ))->toThrow(InvalidMembershipOperation::class);

    expect(MemberDocument::query()->count())->toBe(0);
});

test('private document review is tenant nested and pending only', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create();
    $otherMember = Member::factory()->for($organisation)->create();
    $document = MemberDocument::factory()
        ->for($member, 'documentable')
        ->pending()
        ->create(['organisation_id' => $organisation->id]);
    $review = app(ReviewMemberDocument::class);

    expect(fn () => $review->handle(
        $otherMember,
        $document,
        $actor,
        DocumentVerificationStatus::Verified,
    ))->toThrow(InvalidMembershipOperation::class);

    $verified = $review->handle(
        $member,
        $document,
        $actor,
        DocumentVerificationStatus::Verified,
    );
    $verifiedAt = $verified->verified_at;

    expect($verified->verification_status)->toBe(DocumentVerificationStatus::Verified)
        ->and($verified->verified_by_user_id)->toBe($actor->id)
        ->and(fn () => $review->handle(
            $member,
            $verified,
            $actor,
            DocumentVerificationStatus::Rejected,
            'Attempted second review.',
        ))->toThrow(InvalidMembershipOperation::class)
        ->and($verified->refresh()->verification_status)
        ->toBe(DocumentVerificationStatus::Verified)
        ->and($verified->verified_at?->equalTo($verifiedAt))->toBeTrue()
        ->and($verified->rejection_reason)->toBeNull();
});
