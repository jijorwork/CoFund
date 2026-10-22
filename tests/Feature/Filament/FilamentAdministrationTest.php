<?php

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Actions\ActivateMember;
use App\Domain\Membership\Actions\AddMemberContact;
use App\Domain\Membership\Actions\CreateGroup;
use App\Domain\Membership\Actions\CreateMember;
use App\Domain\Membership\Actions\LinkMemberToUser;
use App\Domain\Membership\Actions\ReviewMemberDocument;
use App\Domain\Membership\Actions\UnlinkMemberFromUser;
use App\Domain\Membership\Actions\UpdateGroup;
use App\Domain\Membership\Actions\UpdateMemberProfile;
use App\Domain\Membership\Actions\UploadMemberDocument;
use App\Domain\Membership\Actions\VerifyMemberContact;
use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Enums\MemberDocumentType;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Organisations\Actions\CreateBranch;
use App\Domain\Organisations\Actions\UpdateBranch;
use App\Domain\Organisations\Actions\UpdateOrganisationProfile;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Accounting\FinancialTransactionResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Groups\Pages\ViewGroup;
use App\Filament\Resources\Groups\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Members\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\OrganisationAccess\OrganisationUserResource;
use App\Filament\Resources\OrganisationAccess\Pages\ListOrganisationUsers;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Resources\RoleAssignments\OrganisationUserRoleResource;
use App\Filament\Resources\RoleAssignments\Pages\ListOrganisationUserRoles;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function filamentAssignment(
    User $user,
    Organisation $organisation,
    SystemRole $role,
    ?Branch $branch = null,
): OrganisationUserRole {
    return OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])
        ->create();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('authorised staff can enter the panel with a safe default context', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Treasurer);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSessionHas('active_context.organisation_public_id', $organisation->public_id)
        ->assertSessionHas('active_context.role_assignment_public_id', $assignment->public_id);
});

test('the panel persistently displays the verified active context', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create(['name' => 'Adom Cooperative']);
    filamentAssignment($user, $organisation, SystemRole::Treasurer);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Active context:')
        ->assertSee('Adom Cooperative')
        ->assertSee('Organisation-wide')
        ->assertSee('Treasurer');
});

test('a member role cannot enter the administration panel', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    filamentAssignment($user, $organisation, SystemRole::Member);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('member resource queries remain isolated to the active organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::MembershipOfficer);
    $linkedUser = User::factory()->create();
    $visible = Member::factory()->for($organisation)->for($linkedUser)->create();
    Member::factory()->for($otherOrganisation)->create();

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    expect(MemberResource::getEloquentQuery()->pluck('public_id')->all())
        ->toBe([$visible->public_id]);

    expect(MemberResource::getEloquentQuery()->firstOrFail())
        ->relationLoaded('branch')->toBeTrue()
        ->relationLoaded('user')->toBeTrue();
});

test('member directory is branch scoped responsive and masks personal contact data', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::BranchManager, $activeBranch);
    $visible = Member::factory()->for($organisation)->for($activeBranch)->create([
        'phone' => '+233241234567',
        'email' => 'ama.private@example.test',
    ]);
    $hidden = Member::factory()->for($organisation)->for($otherBranch)->create();

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $component = Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden])
        ->assertDontSee('+233241234567')
        ->assertDontSee('ama.private@example.test');

    expect($component->instance()->getTable()->getColumn('branch.name')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('phone')?->getVisibleFrom())
        ->toBe('lg')
        ->and($component->instance()->getTable()->getColumn('joined_at')?->getVisibleFrom())
        ->toBe('md');
});

test('member detail uses public UUID routes and rejects foreign tenant records', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::MembershipOfficer);
    $foreignMember = Member::factory()->for($foreignOrganisation)->create();

    expect(MemberResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(MemberResource::getRecordRouteKeyName())->toBe('public_id');

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(MemberResource::getUrl('view', ['record' => $foreignMember->public_id]))
        ->assertNotFound();
});

test('member navigation and direct URLs require the member view permission', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::MembershipOfficer);
    $member = Member::factory()->for($organisation)->create();
    $assignment->role->revokePermissionTo(FoundationPermission::MembersView->value);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    expect(MemberResource::canViewAny())->toBeFalse();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(MemberResource::getUrl('view', ['record' => $member->public_id]))
        ->assertForbidden();
});

test('member detail loads masked profile summaries and status history without sensitive values', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::BranchManager);
    $member = Member::factory()->pending()->for($organisation)->create([
        'phone' => '+233241234567',
        'alternate_phone' => '+233501112222',
        'email' => 'private.member@example.test',
        'national_id_number' => 'GHA-123456789-0',
    ]);

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->assertOk()
        ->assertSee($member->membership_number)
        ->assertDontSee('+233241234567')
        ->assertDontSee('+233501112222')
        ->assertDontSee('private.member@example.test')
        ->assertDontSee('GHA-123456789-0');
});

test('member lifecycle UI delegates activation to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $member = Member::factory()->pending()->for($organisation)->create();
    $activated = $member->replicate()->forceFill([
        'id' => $member->id,
        'status' => MemberStatus::Active,
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $activate = Mockery::mock(ActivateMember::class);
    $activate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Member $selectedMember, User $selectedActor): bool => $selectedMember->is($member)
            && $selectedActor->is($actor))
        ->andReturn($activated);
    app()->instance(ActivateMember::class, $activate);

    Livewire::test(ListMembers::class)
        ->assertActionVisible(TestAction::make('activate')->table($member))
        ->callAction(TestAction::make('activate')->table($member))
        ->assertNotified('Member status updated');
});

test('member lifecycle actions obey status and explicit permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $viewOnlyAssignment = filamentAssignment($actor, $organisation, SystemRole::MembershipOfficer);
    $activeMember = Member::factory()->for($organisation)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($viewOnlyAssignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->assertActionHidden(TestAction::make('suspend')->table($activeMember))
        ->assertActionHidden(TestAction::make('markInactive')->table($activeMember))
        ->assertActionHidden(TestAction::make('exit')->table($activeMember))
        ->assertActionHidden(TestAction::make('markDeceased')->table($activeMember));
});

test('member profile updates delegate only editable fields to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager, $branch);
    $member = Member::factory()->for($organisation)->for($branch)->create([
        'national_id_number' => 'GHA-EXISTING-123',
    ]);
    $updatedMember = $member->replicate()->forceFill([
        'id' => $member->id,
        'first_name' => 'Updated',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $updateMember = Mockery::mock(UpdateMemberProfile::class);
    $updateMember->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            Member $selectedMember,
            array $profile,
            ?Branch $selectedBranch,
        ) use ($member, $branch): bool {
            return $selectedMember->is($member)
                && $selectedBranch?->is($branch) === true
                && $profile['first_name'] === 'Updated'
                && $profile['last_name'] === 'Member'
                && $profile['phone'] === '024 000 0000'
                && ! array_key_exists('national_id_number', $profile)
                && ! array_key_exists('organisation_id', $profile)
                && ! array_key_exists('branch_id', $profile)
                && ! array_key_exists('membership_number', $profile)
                && ! array_key_exists('status', $profile)
                && ! array_key_exists('user_id', $profile)
                && ! array_key_exists('joined_at', $profile);
        })
        ->andReturn($updatedMember);
    app()->instance(UpdateMemberProfile::class, $updateMember);

    Livewire::test(ListMembers::class)
        ->assertActionVisible(TestAction::make('editProfile')->table($member))
        ->callAction(TestAction::make('editProfile')->table($member), data: [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Member',
            'preferred_name' => null,
            'gender' => null,
            'date_of_birth' => null,
            'phone' => '024 000 0000',
            'alternate_phone' => null,
            'email' => 'updated@example.test',
            'national_id_type' => 'Ghana Card',
            'national_id_number' => '',
            'occupation' => null,
            'residential_address' => null,
            'postal_address' => null,
            'notes' => null,
            'organisation_id' => Organisation::factory()->create()->id,
            'branch_id' => Branch::factory()->create()->id,
            'membership_number' => 'TAMPERED',
            'status' => MemberStatus::Exited->value,
            'user_id' => User::factory()->create()->id,
            'joined_at' => now()->addYear()->toDateString(),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member profile updated');
});

test('member profile updates can replace but never reveal the encrypted national id', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $member = Member::factory()->for($organisation)->create([
        'first_name' => 'Ama',
        'last_name' => 'Mensah',
        'phone' => '+233241111111',
        'national_id_number' => 'GHA-OLD-SECRET',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->mountAction(TestAction::make('editProfile')->table($member))
        ->assertDontSee('GHA-OLD-SECRET')
        ->fillForm([
            'first_name' => 'Ama',
            'middle_name' => null,
            'last_name' => 'Mensah',
            'preferred_name' => null,
            'gender' => null,
            'date_of_birth' => null,
            'phone' => '+233241111111',
            'alternate_phone' => null,
            'email' => null,
            'national_id_type' => 'Ghana Card',
            'national_id_number' => 'GHA-NEW-SECRET',
            'occupation' => null,
            'residential_address' => null,
            'postal_address' => null,
            'notes' => null,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Member profile updated')
        ->assertDontSee('GHA-NEW-SECRET');

    expect($member->refresh()->national_id_number)->toBe('GHA-NEW-SECRET');
});

test('member profile update is hidden without members update permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::MembershipOfficer);
    $member = Member::factory()->for($organisation)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->assertActionHidden(TestAction::make('editProfile')->table($member));
});

test('member registration is permission gated and delegates to CreateMember', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $createdMember = Member::factory()->for($organisation)->for($branch)->make();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $createMember = Mockery::mock(CreateMember::class);
    $createMember->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            User $selectedActor,
            array $profile,
            ?Branch $selectedBranch,
        ) use ($actor, $branch): bool {
            return $selectedActor->is($actor)
                && $selectedBranch?->is($branch) === true
                && $profile['first_name'] === 'Efua'
                && $profile['last_name'] === 'Owusu'
                && $profile['phone'] === '024 123 4567'
                && $profile['national_id_number'] === 'GHA-PRIVATE-123'
                && ! array_key_exists('organisation_id', $profile)
                && ! array_key_exists('status', $profile);
        })
        ->andReturn($createdMember);
    app()->instance(CreateMember::class, $createMember);

    Livewire::test(ListMembers::class)
        ->assertActionVisible(TestAction::make('registerMember')->table())
        ->callAction(TestAction::make('registerMember')->table(), data: [
            'first_name' => 'Efua',
            'middle_name' => null,
            'last_name' => 'Owusu',
            'preferred_name' => null,
            'gender' => null,
            'date_of_birth' => null,
            'phone' => '024 123 4567',
            'alternate_phone' => null,
            'email' => 'efua.private@example.test',
            'occupation' => null,
            'residential_address' => null,
            'postal_address' => null,
            'branch_id' => $branch->id,
            'joined_at' => now()->toDateString(),
            'national_id_type' => 'Ghana Card',
            'national_id_number' => 'GHA-PRIVATE-123',
            'notes' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member registered');
});

test('member registration derives tenant branch audit and keeps submitted personal data masked', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager, $branch);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->callAction(TestAction::make('registerMember')->table(), data: [
            'first_name' => 'Akosua',
            'last_name' => 'Boateng',
            'phone' => '024 555 0101',
            'email' => 'akosua.private@example.test',
            'branch_id' => $branch->id,
            'joined_at' => now()->toDateString(),
            'national_id_type' => 'Ghana Card',
            'national_id_number' => 'GHA-SECRET-999',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member registered')
        ->assertDontSee('024 555 0101')
        ->assertDontSee('akosua.private@example.test')
        ->assertDontSee('GHA-SECRET-999');

    $member = Member::query()->where('first_name', 'Akosua')->firstOrFail();
    $history = $member->statusHistories()->sole();

    expect($member->organisation_id)->toBe($organisation->id)
        ->and($member->branch_id)->toBe($branch->id)
        ->and($member->created_by_user_id)->toBe($actor->id)
        ->and($member->membership_number)->toStartWith('UNITY-')
        ->and($member->phone)->toBe('+233245550101')
        ->and($member->status)->toBe(MemberStatus::Pending)
        ->and($history->changed_by_user_id)->toBe($actor->id)
        ->and($history->acting_role_id)->toBe($assignment->role_id);
});

test('member registration rejects branches outside the verified active context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager, $branch);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->callAction(TestAction::make('registerMember')->table(), data: [
            'first_name' => 'Abena',
            'last_name' => 'Mensah',
            'phone' => '0200000000',
            'branch_id' => $otherBranch->id,
            'joined_at' => now()->toDateString(),
        ])
        ->assertHasFormErrors(['branch_id'])
        ->assertNotNotified('Member registered');

    expect(Member::query()->where('first_name', 'Abena')->exists())->toBeFalse();
});

test('member registration is hidden without members create permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::MembershipOfficer);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->assertActionHidden(TestAction::make('registerMember')->table());
});

test('member suspension through Filament records append only actor history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $member = Member::factory()->for($organisation)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListMembers::class)
        ->callAction(TestAction::make('suspend')->table($member), data: [
            'reason' => 'Membership compliance review.',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member status updated');

    $history = $member->statusHistories()->sole();

    expect($member->refresh()->status)->toBe(MemberStatus::Suspended)
        ->and($history->changed_by_user_id)->toBe($actor->id)
        ->and($history->acting_role_id)->toBe($assignment->role_id)
        ->and($history->reason)->toBe('Membership compliance review.');
});

test('member detail exposes contacts only with contact management permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->assertSeeLivewire(ContactsRelationManager::class);

    $viewOnlyAssignment = filamentAssignment(
        User::factory()->create(),
        $organisation,
        SystemRole::MembershipOfficer,
    );
    $context->setRoleAssignment($viewOnlyAssignment->load(['role', 'user']));

    expect(ContactsRelationManager::canViewForRecord($member, ViewMember::class))
        ->toBeFalse();
});

test('contacts relation is parent contained responsive and masks personal data', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $otherMember = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $visible = MemberContact::factory()->for($member)->beneficiary('35.00')->create([
        'phone' => '+233241234567',
        'email' => 'beneficiary.private@example.test',
        'national_id_number' => 'GHA-SECRET-1234',
    ]);
    $hidden = MemberContact::factory()->for($otherMember)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $component = Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden])
        ->assertDontSee('+233241234567')
        ->assertDontSee('beneficiary.private@example.test')
        ->assertDontSee('GHA-SECRET-1234');

    expect($component->instance()->getTable()->getColumn('relationship')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('phone')?->getVisibleFrom())
        ->toBe('lg')
        ->and($component->instance()->getTable()->getColumn('is_primary')?->getVisibleFrom())
        ->toBe('xl');
});

test('adding a contact delegates to the domain action without tenant-controlled fields', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $contact = MemberContact::factory()->for($member)->make();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $addContact = Mockery::mock(AddMemberContact::class);
    $addContact->shouldReceive('handle')
        ->once()
        ->withArgs(function (Member $selectedMember, array $attributes) use ($member): bool {
            return $selectedMember->is($member)
                && $attributes['contact_type'] === MemberContactType::Beneficiary->value
                && $attributes['full_name'] === 'Adwoa Owusu'
                && (float) $attributes['percentage_entitlement'] === 40.0
                && $attributes['national_id_number'] === 'PRIVATE-ID-44'
                && ! array_key_exists('organisation_id', $attributes)
                && ! array_key_exists('member_id', $attributes)
                && ! array_key_exists('status', $attributes);
        })
        ->andReturn($contact);
    app()->instance(AddMemberContact::class, $addContact);

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->callAction(TestAction::make('addContact')->table(), data: [
            'contact_type' => MemberContactType::Beneficiary->value,
            'full_name' => 'Adwoa Owusu',
            'relationship' => 'Child',
            'phone' => '0240000000',
            'alternate_phone' => null,
            'email' => 'adwoa.private@example.test',
            'address' => null,
            'national_id_type' => 'Ghana Card',
            'national_id_number' => 'PRIVATE-ID-44',
            'percentage_entitlement' => '40.00',
            'is_primary' => true,
            'is_emergency_contact' => false,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Contact added');
});

test('beneficiary creation enforces aggregate allocation and masks submitted values', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    MemberContact::factory()->for($member)->beneficiary('75.00')->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->callAction(TestAction::make('addContact')->table(), data: [
            'contact_type' => MemberContactType::Beneficiary->value,
            'full_name' => 'Excess Beneficiary',
            'relationship' => 'Child',
            'phone' => '0249999999',
            'email' => 'excess.private@example.test',
            'national_id_number' => 'EXCESS-PRIVATE-ID',
            'percentage_entitlement' => '25.01',
            'is_primary' => false,
            'is_emergency_contact' => false,
        ])
        ->assertHasFormErrors(['percentage_entitlement'])
        ->assertNotNotified('Contact added')
        ->assertDontSee('0249999999')
        ->assertDontSee('excess.private@example.test')
        ->assertDontSee('EXCESS-PRIVATE-ID');

    expect($member->contacts()->where('full_name', 'Excess Beneficiary')->exists())
        ->toBeFalse();
});

test('contact verification delegates to the domain action with the verified actor', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $contact = MemberContact::factory()->for($member)->create();
    $verified = $contact->replicate()->forceFill([
        'id' => $contact->id,
        'verified_at' => now(),
        'verified_by_user_id' => $actor->id,
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $verifyContact = Mockery::mock(VerifyMemberContact::class);
    $verifyContact->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            Member $selectedMember,
            MemberContact $selectedContact,
            User $selectedActor,
        ): bool => $selectedMember->is($member)
            && $selectedContact->is($contact)
            && $selectedActor->is($actor))
        ->andReturn($verified);
    app()->instance(VerifyMemberContact::class, $verifyContact);

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->assertActionVisible(TestAction::make('verify')->table($contact))
        ->callAction(TestAction::make('verify')->table($contact))
        ->assertNotified('Contact verified');
});

test('contact deactivation preserves the record and removes active primary scope', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $contact = MemberContact::factory()->for($member)->primary()->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->callAction(TestAction::make('deactivate')->table($contact))
        ->assertNotified('Contact deactivated');

    expect($contact->refresh()->status)->toBe(MemberContactStatus::Inactive)
        ->and($contact->primary_scope_key)->toBeNull()
        ->and($contact->exists)->toBeTrue();
});

test('member account linking delegates only active organisation users to the domain action', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create([
        'name' => 'Linked Account',
        'email' => 'linked.account@example.test',
    ]);
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->active()->for($organisation)->create();
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $linked = $member->replicate()->forceFill([
        'id' => $member->id,
        'user_id' => $target->id,
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $linkMember = Mockery::mock(LinkMemberToUser::class);
    $linkMember->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Member $selectedMember, User $selectedUser): bool => $selectedMember->is($member)
            && $selectedUser->is($target))
        ->andReturn($linked);
    app()->instance(LinkMemberToUser::class, $linkMember);

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->assertActionVisible('linkAccount')
        ->assertActionHidden('unlinkAccount')
        ->callAction('linkAccount', data: [
            'user_id' => $target->id,
            'organisation_id' => Organisation::factory()->create()->id,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Login account linked');
});

test('member account links reject foreign users and hide actions without permission', function () {
    $actor = User::factory()->create();
    $foreignUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $member = Member::factory()->active()->for($organisation)->create();
    OrganisationUser::factory()->for($foreignUser)->for($foreignOrganisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->callAction('linkAccount', data: ['user_id' => $foreignUser->id])
        ->assertHasFormErrors(['user_id'])
        ->assertNotNotified('Login account linked');

    $collector = User::factory()->create();
    $collectorAssignment = filamentAssignment(
        $collector,
        $organisation,
        SystemRole::Collector,
    );
    $this->actingAs($collector);
    $context->setRoleAssignment($collectorAssignment->load(['role', 'user']));

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->assertActionHidden('linkAccount')
        ->assertActionHidden('unlinkAccount');
});

test('member account unlinking delegates without deleting either record', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->active()->for($organisation)->for($target)->create([
        'active_user_link_key' => Member::activeUserLinkKey($organisation, $target),
    ]);
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $unlinked = $member->replicate()->forceFill([
        'id' => $member->id,
        'user_id' => null,
        'active_user_link_key' => null,
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $unlinkMember = Mockery::mock(UnlinkMemberFromUser::class);
    $unlinkMember->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Member $selectedMember): bool => $selectedMember->is($member))
        ->andReturn($unlinked);
    app()->instance(UnlinkMemberFromUser::class, $unlinkMember);

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->assertActionHidden('linkAccount')
        ->assertActionVisible('unlinkAccount')
        ->callAction('unlinkAccount')
        ->assertNotified('Login account unlinked');

    expect($member->exists)->toBeTrue()
        ->and($target->exists)->toBeTrue();
});

test('member documents are visible only with explicit private document permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ViewMember::class, ['record' => $member->public_id])
        ->set('activeRelationManager', 1)
        ->assertSee('Private documents');

    expect(DocumentsRelationManager::canViewForRecord($member, ViewMember::class))
        ->toBeTrue();

    $collector = User::factory()->create();
    $collectorAssignment = filamentAssignment(
        $collector,
        $organisation,
        SystemRole::Collector,
    );
    $this->actingAs($collector);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($collectorAssignment->load(['role', 'user']));

    expect(DocumentsRelationManager::canViewForRecord($member, ViewMember::class))
        ->toBeFalse();
});

test('member document upload delegates temporary files to private domain storage', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $uploaded = MemberDocument::factory()->make([
        'organisation_id' => $organisation->id,
        'documentable_id' => $member->id,
        'document_type' => MemberDocumentType::NationalId,
        'original_name' => 'identity.pdf',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $uploadDocument = Mockery::mock(UploadMemberDocument::class);
    $uploadDocument->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            Member $selectedMember,
            User $selectedActor,
            string $type,
            UploadedFile $file,
        ): bool => $selectedMember->is($member)
            && $selectedActor->is($actor)
            && $type === MemberDocumentType::NationalId->value
            && $file->getClientOriginalName() === 'identity.pdf')
        ->andReturn($uploaded);
    app()->instance(UploadMemberDocument::class, $uploadDocument);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->assertActionVisible(TestAction::make('uploadDocument')->table())
        ->callAction(TestAction::make('uploadDocument')->table(), data: [
            'document_type' => MemberDocumentType::NationalId->value,
            'file' => UploadedFile::fake()->create(
                'identity.pdf',
                100,
                'application/pdf',
            ),
            'disk' => 'public',
            'path' => 'tampered/public/path.pdf',
            'verification_status' => DocumentVerificationStatus::Verified->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Document uploaded')
        ->assertDontSee('tampered/public/path.pdf');
});

test('member document review delegates pending metadata without exposing storage fields', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $document = MemberDocument::factory()->for($member, 'documentable')->pending()->create([
        'organisation_id' => $organisation->id,
        'path' => 'private/secret/document.pdf',
    ]);
    $foreignDocument = MemberDocument::factory()
        ->for(Member::factory()->for(Organisation::factory()), 'documentable')
        ->pending()
        ->create();
    $verified = $document->replicate()->forceFill([
        'id' => $document->id,
        'verification_status' => DocumentVerificationStatus::Verified,
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $reviewDocument = Mockery::mock(ReviewMemberDocument::class);
    $reviewDocument->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            Member $selectedMember,
            MemberDocument $selectedDocument,
            User $selectedActor,
            DocumentVerificationStatus $status,
            ?string $reason,
        ): bool => $selectedMember->is($member)
            && $selectedDocument->is($document)
            && $selectedActor->is($actor)
            && $status === DocumentVerificationStatus::Verified
            && $reason === null)
        ->andReturn($verified);
    app()->instance(ReviewMemberDocument::class, $reviewDocument);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])
        ->assertCanSeeTableRecords([$document])
        ->assertCanNotSeeTableRecords([$foreignDocument])
        ->assertActionVisible(TestAction::make('verify')->table($document))
        ->callAction(TestAction::make('verify')->table($document))
        ->assertNotified('Document verified')
        ->assertDontSee('private/secret/document.pdf')
        ->assertDontSee($document->checksum);
});

test('private member document downloads enforce member and role context', function () {
    Storage::fake('local');
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $otherMember = Member::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $document = MemberDocument::factory()->for($member, 'documentable')->create([
        'organisation_id' => $organisation->id,
        'path' => 'membership/private/document.pdf',
    ]);
    $otherDocument = MemberDocument::factory()->for($otherMember, 'documentable')->create([
        'organisation_id' => $organisation->id,
        'path' => 'membership/private/other.pdf',
    ]);
    Storage::disk('local')->put($document->path, 'private-document');
    Storage::disk('local')->put($otherDocument->path, 'other-private-document');

    $session = [
        'active_context' => [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => null,
            'role_assignment_public_id' => $assignment->public_id,
        ],
    ];

    $downloadUrl = route('api.v1.members.documents.download', [
        $member->public_id,
        $document->public_id,
    ]);

    $this->actingAs($actor)
        ->withSession($session)
        ->get($downloadUrl)
        ->assertSuccessful();

    $this->withSession($session)
        ->get(route('api.v1.members.documents.download', [
            $member->public_id,
            $otherDocument->public_id,
        ]))
        ->assertNotFound();

    $collector = User::factory()->create();
    $collectorAssignment = filamentAssignment(
        $collector,
        $organisation,
        SystemRole::Collector,
    );

    $this->actingAs($collector)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $collectorAssignment->public_id,
            ],
        ])
        ->get($downloadUrl)
        ->assertForbidden();
});

test('group resource is tenant and branch isolated with eager loaded summaries', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::BranchManager,
        $activeBranch,
    );
    $visibleGroup = Group::factory()->for($organisation)->for($activeBranch)->create();
    $otherBranchGroup = Group::factory()->for($organisation)->for($otherBranch)->create();
    $organisationWideGroup = Group::factory()->for($organisation)->create(['branch_id' => null]);
    $foreignGroup = Group::factory()->for($foreignOrganisation)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListGroups::class)
        ->assertCanSeeTableRecords([$visibleGroup])
        ->assertCanNotSeeTableRecords([
            $otherBranchGroup,
            $organisationWideGroup,
            $foreignGroup,
        ]);

    $loaded = GroupResource::getEloquentQuery()->findOrFail($visibleGroup->id);

    expect($loaded)
        ->relationLoaded('branch')->toBeTrue()
        ->relationLoaded('createdBy')->toBeTrue()
        ->and($loaded->active_members_count)->toBeInt()
        ->and(GroupResource::getGlobalSearchResultUrl($loaded))
        ->toContain($visibleGroup->public_id)
        ->and(GroupResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(GroupResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit']);
});

test('group direct URLs reject foreign organisation UUIDs and permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignGroup = Group::factory()->for(Organisation::factory())->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);

    $session = [
        'active_context' => [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => null,
            'role_assignment_public_id' => $assignment->public_id,
        ],
    ];

    $this->actingAs($actor)
        ->withSession($session)
        ->get(GroupResource::getUrl('view', ['record' => $foreignGroup->public_id]))
        ->assertNotFound();

    $viewOnlyActor = User::factory()->create();
    $viewOnlyAssignment = filamentAssignment(
        $viewOnlyActor,
        $organisation,
        SystemRole::MembershipOfficer,
    );

    $this->actingAs($viewOnlyActor)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $viewOnlyAssignment->public_id,
            ],
        ])
        ->get(GroupResource::getUrl('index'))
        ->assertForbidden();
});

test('group creation delegates tenant safe profile and branch data', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager);
    $createdGroup = Group::factory()->for($organisation)->for($branch)->make();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $createGroup = Mockery::mock(CreateGroup::class);
    $createGroup->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            User $selectedActor,
            array $attributes,
            ?Branch $selectedBranch,
        ) use ($actor, $branch): bool {
            return $selectedActor->is($actor)
                && $selectedBranch?->is($branch) === true
                && $attributes === [
                    'name' => 'Market Circle',
                    'code' => 'market',
                    'group_type' => GroupType::SusuCircle,
                    'description' => 'Market traders',
                ]
                && ! array_key_exists('organisation_id', $attributes)
                && ! array_key_exists('status', $attributes)
                && ! array_key_exists('created_by_user_id', $attributes);
        })
        ->andReturn($createdGroup);
    app()->instance(CreateGroup::class, $createGroup);

    Livewire::test(ListGroups::class)
        ->assertActionVisible(TestAction::make('createGroup')->table())
        ->callAction(TestAction::make('createGroup')->table(), data: [
            'name' => 'Market Circle',
            'code' => 'market',
            'group_type' => GroupType::SusuCircle->value,
            'description' => 'Market traders',
            'branch_id' => $branch->id,
            'organisation_id' => Organisation::factory()->create()->id,
            'status' => GroupStatus::Suspended->value,
            'created_by_user_id' => User::factory()->create()->id,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Group created');
});

test('group profile updates delegate without changing branch or status', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::BranchManager,
        $branch,
    );
    $group = Group::factory()->for($organisation)->for($branch)->create();
    $updatedGroup = $group->replicate()->forceFill([
        'id' => $group->id,
        'name' => 'Updated Group',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $updateGroup = Mockery::mock(UpdateGroup::class);
    $updateGroup->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            Group $selectedGroup,
            array $attributes,
            ?Branch $selectedBranch,
        ) use ($group, $branch): bool {
            return $selectedGroup->is($group)
                && $selectedBranch?->is($branch) === true
                && $attributes['name'] === 'Updated Group'
                && $attributes['code'] === 'updated'
                && ! array_key_exists('branch_id', $attributes)
                && ! array_key_exists('status', $attributes);
        })
        ->andReturn($updatedGroup);
    app()->instance(UpdateGroup::class, $updateGroup);

    Livewire::test(ListGroups::class)
        ->assertActionVisible(TestAction::make('editProfile')->table($group))
        ->callAction(TestAction::make('editProfile')->table($group), data: [
            'name' => 'Updated Group',
            'code' => 'updated',
            'group_type' => GroupType::CommunityUnit->value,
            'description' => null,
            'branch_id' => Branch::factory()->for($organisation)->create()->id,
            'status' => GroupStatus::Inactive->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Group profile updated');
});

test('group mutation actions are hidden from view only roles', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::Collector);
    $group = Group::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->create();
    $membership = GroupMembership::factory()
        ->for($group)
        ->for($member)
        ->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListGroups::class)
        ->assertActionHidden(TestAction::make('createGroup')->table())
        ->assertActionHidden(TestAction::make('editProfile')->table($group));

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewGroup::class,
    ])
        ->assertActionHidden(TestAction::make('addMember')->table())
        ->assertActionHidden(TestAction::make('removeMember')->table($membership));
});

test('group membership actions preserve history and reject foreign members', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::BranchManager,
        $branch,
    );
    $group = Group::factory()->for($organisation)->for($branch)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    $foreignMember = Member::factory()->for(Organisation::factory())->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ViewGroup::class, ['record' => $group->public_id])
        ->assertSeeLivewire(MembershipsRelationManager::class);

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewGroup::class,
    ])
        ->callAction(TestAction::make('addMember')->table(), data: [
            'member_uuid' => $foreignMember->public_id,
        ])
        ->assertHasFormErrors(['member_uuid']);

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewGroup::class,
    ])
        ->callAction(TestAction::make('addMember')->table(), data: [
            'member_uuid' => $member->public_id,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member added to group');

    $membership = GroupMembership::query()
        ->whereBelongsTo($group)
        ->whereBelongsTo($member)
        ->sole();

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewGroup::class,
    ])
        ->assertCanSeeTableRecords([$membership])
        ->callAction(TestAction::make('removeMember')->table($membership))
        ->assertNotified('Member removed from group');

    expect($membership->refresh()->status)->toBe(GroupMembershipStatus::Left)
        ->and($membership->left_at)->not->toBeNull()
        ->and($membership->exists)->toBeTrue();
});

test('financial transactions are immutable through Filament resources', function () {
    $transaction = new FinancialTransaction;

    expect(FinancialTransactionResource::canCreate())->toBeFalse()
        ->and(FinancialTransactionResource::canEdit($transaction))->toBeFalse()
        ->and(FinancialTransactionResource::canDelete($transaction))->toBeFalse()
        ->and(FinancialTransactionResource::canReplicate($transaction))->toBeFalse();
});

test('branch resource lists only branches in the active organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Treasurer);
    $visibleBranches = Branch::factory()->count(2)->for($organisation)->create();
    $hiddenBranch = Branch::factory()->for($otherOrganisation)->create();

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords($visibleBranches)
        ->assertCanNotSeeTableRecords([$hiddenBranch]);
});

test('branch global search remains scoped to the verified active context', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create([
        'name' => 'Central Market',
    ]);
    $sameTenantBranch = Branch::factory()->for($organisation)->create([
        'name' => 'North Market',
    ]);
    $foreignBranch = Branch::factory()->for($otherOrganisation)->create([
        'name' => 'Central Market',
    ]);
    $assignment = filamentAssignment(
        $user,
        $organisation,
        SystemRole::BranchManager,
        $activeBranch,
    );

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    $results = BranchResource::getGlobalSearchResults('Market');

    expect($results)->toHaveCount(1)
        ->and($results->first()?->title)->toBe($activeBranch->name)
        ->and($results->first()?->url)
        ->toBe(BranchResource::getUrl('view', ['record' => $activeBranch->public_id]))
        ->and($results->pluck('url')->all())
        ->not->toContain(BranchResource::getUrl('view', ['record' => $sameTenantBranch->public_id]))
        ->not->toContain(BranchResource::getUrl('view', ['record' => $foreignBranch->public_id]));
});

test('branch-specific context exposes only its assigned branch', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $user,
        $organisation,
        SystemRole::BranchManager,
        $activeBranch,
    );

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    $component = Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords([$activeBranch])
        ->assertCanNotSeeTableRecords([$otherBranch]);

    expect($component->instance()->getTable()->getColumn('members_count')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('user_role_assignments_count')?->getVisibleFrom())
        ->toBe('lg');
});

test('branch direct URLs cannot resolve another organisation record', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Treasurer);
    $foreignBranch = Branch::factory()->for($otherOrganisation)->create();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(BranchResource::getUrl('view', ['record' => $foreignBranch->public_id]))
        ->assertNotFound();
});

test('staff without branch permission cannot access the branch resource directly', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::MembershipOfficer);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    expect(BranchResource::canAccess())->toBeFalse();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(BranchResource::getUrl('index'))
        ->assertForbidden();
});

test('branch administration is read only and uses public UUID routes', function () {
    $branch = Branch::factory()->make();

    expect(BranchResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(BranchResource::canCreate())->toBeFalse()
        ->and(BranchResource::canEdit($branch))->toBeFalse()
        ->and(BranchResource::canDelete($branch))->toBeFalse()
        ->and(BranchResource::getRecordRouteKeyName())->toBe('public_id');
});

test('branch creation is hidden without permission', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Treasurer);

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListBranches::class)
        ->assertActionHidden(TestAction::make('createBranch')->table())
        ->assertActionHidden(TestAction::make('editProfile')->table(
            Branch::factory()->for($organisation)->create(),
        ));
});

test('branch creation delegates tenant-safe data to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $createdBranch = Branch::factory()->for($organisation)->make([
        'name' => 'North Office',
        'code' => 'NORTH',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $createBranch = Mockery::mock(CreateBranch::class);
    $createBranch->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            Organisation $selectedOrganisation,
            array $attributes,
        ) use ($organisation): bool {
            return $selectedOrganisation->is($organisation)
                && $attributes === [
                    'name' => 'North Office',
                    'code' => 'north',
                    'phone' => '+233 30 200 0000',
                    'email' => 'north@example.test',
                    'address' => 'North district',
                ]
                && ! array_key_exists('organisation_id', $attributes)
                && ! array_key_exists('status', $attributes);
        })
        ->andReturn($createdBranch);
    app()->instance(CreateBranch::class, $createBranch);

    Livewire::test(ListBranches::class)
        ->assertActionVisible(TestAction::make('createBranch')->table())
        ->callAction(TestAction::make('createBranch')->table(), data: [
            'name' => 'North Office',
            'code' => 'north',
            'phone' => '+233 30 200 0000',
            'email' => 'north@example.test',
            'address' => 'North district',
            'organisation_id' => Organisation::factory()->create()->id,
            'status' => BranchStatus::Suspended->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Branch created');
});

test('branch creation is unavailable from a branch-specific context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::OrganisationAdmin,
        $branch,
    );

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListBranches::class)
        ->assertActionHidden(TestAction::make('createBranch')->table());
});

test('branch profile updates delegate tenant-safe data to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create([
        'status' => BranchStatus::Active,
    ]);
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $updatedBranch = $branch->replicate()->forceFill([
        'id' => $branch->id,
        'name' => 'Updated Branch',
        'code' => 'UPDATED',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $updateBranch = Mockery::mock(UpdateBranch::class);
    $updateBranch->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            Branch $selectedBranch,
            array $attributes,
        ) use ($branch): bool {
            return $selectedBranch->is($branch)
                && $attributes === [
                    'name' => 'Updated Branch',
                    'code' => 'updated',
                    'phone' => '+233 24 000 0000',
                    'email' => 'updated@example.test',
                    'address' => 'Updated address',
                ]
                && ! array_key_exists('organisation_id', $attributes)
                && ! array_key_exists('status', $attributes);
        })
        ->andReturn($updatedBranch);
    app()->instance(UpdateBranch::class, $updateBranch);

    Livewire::test(ListBranches::class)
        ->assertActionVisible(TestAction::make('editProfile')->table($branch))
        ->callAction(TestAction::make('editProfile')->table($branch), data: [
            'name' => 'Updated Branch',
            'code' => 'updated',
            'phone' => '+233 24 000 0000',
            'email' => 'updated@example.test',
            'address' => 'Updated address',
            'organisation_id' => Organisation::factory()->create()->id,
            'status' => BranchStatus::Suspended->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Branch profile updated');
});

test('branch profile updates remain inside an active branch context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::OrganisationAdmin,
        $activeBranch,
    );

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords([$activeBranch])
        ->assertCanNotSeeTableRecords([$otherBranch])
        ->assertActionVisible(TestAction::make('editProfile')->table($activeBranch));
});

test('organisation profile exposes only the verified active organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Auditor);

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisations::class)
        ->assertCanSeeTableRecords([$organisation])
        ->assertCanNotSeeTableRecords([$foreignOrganisation]);

    expect(OrganisationResource::getEloquentQuery()->firstOrFail())
        ->relationLoaded('branches')
        ->toBeFalse()
        ->and(OrganisationResource::getEloquentQuery()->firstOrFail()->branches_count)
        ->toBeInt();
});

test('organisation profile updates delegate only safe fields to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $updatedOrganisation = $organisation->replicate()->forceFill([
        'id' => $organisation->id,
        'name' => 'Updated Organisation',
        'code' => 'UPDATED',
    ]);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    $updateOrganisation = Mockery::mock(UpdateOrganisationProfile::class);
    $updateOrganisation->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            Organisation $selectedOrganisation,
            array $attributes,
        ) use ($organisation): bool {
            return $selectedOrganisation->is($organisation)
                && $attributes === [
                    'name' => 'Updated Organisation',
                    'code' => 'updated',
                    'phone' => '+233 24 000 0000',
                    'email' => 'updated@example.test',
                    'address' => 'Updated address',
                ]
                && ! array_key_exists('status', $attributes)
                && ! array_key_exists('organisation_type', $attributes)
                && ! array_key_exists('currency_code', $attributes)
                && ! array_key_exists('timezone', $attributes)
                && ! array_key_exists('subscription_plan_id', $attributes);
        })
        ->andReturn($updatedOrganisation);
    app()->instance(UpdateOrganisationProfile::class, $updateOrganisation);

    Livewire::test(ListOrganisations::class)
        ->assertActionVisible(TestAction::make('editProfile')->table($organisation))
        ->callAction(TestAction::make('editProfile')->table($organisation), data: [
            'name' => 'Updated Organisation',
            'code' => 'updated',
            'phone' => '+233 24 000 0000',
            'email' => 'updated@example.test',
            'address' => 'Updated address',
            'status' => OrganisationStatus::Suspended->value,
            'organisation_type' => 'susu',
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'subscription_plan_id' => 999,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Organisation profile updated');
});

test('organisation profile update is hidden outside organisation-wide context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::OrganisationAdmin,
        $branch,
    );

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisations::class)
        ->assertActionHidden(TestAction::make('editProfile')->table($organisation));
});

test('organisation profile update is hidden without update permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::Auditor);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisations::class)
        ->assertActionHidden(TestAction::make('editProfile')->table($organisation));
});

test('organisation profile rejects a foreign organisation UUID', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Auditor);

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(OrganisationResource::getUrl('view', ['record' => $foreignOrganisation->public_id]))
        ->assertNotFound();
});

test('organisation access is tenant isolated and eager loads users', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::OrganisationAdmin);
    $visibleAccess = OrganisationUser::factory()->for($organisation)->create();
    $foreignAccess = OrganisationUser::factory()->for($foreignOrganisation)->create();

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisationUsers::class)
        ->assertCanSeeTableRecords([$visibleAccess])
        ->assertCanNotSeeTableRecords([$foreignAccess]);

    expect(OrganisationUserResource::getEloquentQuery()->findOrFail($visibleAccess->id))
        ->relationLoaded('user')
        ->toBeTrue()
        ->and($visibleAccess->public_id)
        ->toBeUuid();
});

test('branch context limits organisation access to people with relevant active roles', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::BranchManager, $activeBranch);
    $visibleUser = User::factory()->create();
    $hiddenUser = User::factory()->create();
    $organisationWideUser = User::factory()->create();
    filamentAssignment($visibleUser, $organisation, SystemRole::MembershipOfficer, $activeBranch);
    filamentAssignment($hiddenUser, $organisation, SystemRole::MembershipOfficer, $otherBranch);
    filamentAssignment($organisationWideUser, $organisation, SystemRole::Auditor);
    $visibleAccess = OrganisationUser::query()->whereBelongsTo($visibleUser)->firstOrFail();
    $hiddenAccess = OrganisationUser::query()->whereBelongsTo($hiddenUser)->firstOrFail();
    $organisationWideAccess = OrganisationUser::query()->whereBelongsTo($organisationWideUser)->firstOrFail();

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisationUsers::class)
        ->assertCanSeeTableRecords([$visibleAccess, $organisationWideAccess])
        ->assertCanNotSeeTableRecords([$hiddenAccess]);
});

test('organisation access global search is isolated to the verified active context', function () {
    $actor = User::factory()->create();
    $visibleUser = User::factory()->create(['name' => 'Shared Search Name']);
    $foreignUser = User::factory()->create(['name' => 'Shared Search Name']);
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $visibleAccess = OrganisationUser::factory()->for($visibleUser)->for($organisation)->create();
    $foreignAccess = OrganisationUser::factory()->for($foreignUser)->for($foreignOrganisation)->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    $results = OrganisationUserResource::getGlobalSearchResults('Shared Search Name');

    expect($results)->toHaveCount(1)
        ->and($results->first()?->url)
        ->toBe(OrganisationUserResource::getUrl('view', ['record' => $visibleAccess->public_id]))
        ->not->toBe(OrganisationUserResource::getUrl('view', ['record' => $foreignAccess->public_id]));
});

test('organisation access rejects a foreign access UUID', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::OrganisationAdmin);
    $foreignAccess = OrganisationUser::factory()->for($foreignOrganisation)->create();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(OrganisationUserResource::getUrl('view', ['record' => $foreignAccess->public_id]))
        ->assertNotFound();
});

test('role assignments are tenant isolated and never expose platform administrator assignments', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::OrganisationAdmin);
    $visibleAssignment = filamentAssignment(User::factory()->create(), $organisation, SystemRole::Auditor);
    $foreignAssignment = filamentAssignment(User::factory()->create(), $foreignOrganisation, SystemRole::Auditor);
    $platformAssignment = OrganisationUserRole::factory()
        ->for(User::factory())
        ->for($organisation)
        ->state(['role_id' => Role::findByName(SystemRole::PlatformAdmin->value)->getKey()])
        ->create();

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisationUserRoles::class)
        ->assertCanSeeTableRecords([$visibleAssignment])
        ->assertCanNotSeeTableRecords([$foreignAssignment, $platformAssignment]);

    expect(OrganisationUserRoleResource::getEloquentQuery()->findOrFail($visibleAssignment->id))
        ->relationLoaded('user')
        ->toBeTrue()
        ->and(OrganisationUserRoleResource::getEloquentQuery()->findOrFail($visibleAssignment->id))
        ->relationLoaded('role')
        ->toBeTrue();
});

test('branch context shows only branch-relevant and organisation-wide role assignments', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::BranchManager, $activeBranch);
    $visibleAssignment = filamentAssignment(User::factory()->create(), $organisation, SystemRole::MembershipOfficer, $activeBranch);
    $hiddenAssignment = filamentAssignment(User::factory()->create(), $organisation, SystemRole::MembershipOfficer, $otherBranch);
    $organisationWideAssignment = filamentAssignment(User::factory()->create(), $organisation, SystemRole::Auditor);

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    $component = Livewire::test(ListOrganisationUserRoles::class)
        ->assertCanSeeTableRecords([$visibleAssignment, $organisationWideAssignment])
        ->assertCanNotSeeTableRecords([$hiddenAssignment]);

    expect($component->instance()->getTable()->getColumn('branch.name')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('starts_at')?->getVisibleFrom())
        ->toBe('lg')
        ->and($component->instance()->getTable()->getColumn('ends_at')?->getVisibleFrom())
        ->toBe('xl');
});

test('role assignment branch filters expose only branches in the verified context', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $sameOrganisationBranch = Branch::factory()->for($organisation)->create();
    $foreignBranch = Branch::factory()->for($foreignOrganisation)->create();
    $assignment = filamentAssignment(
        $actor,
        $organisation,
        SystemRole::OrganisationAdmin,
        $activeBranch,
    );

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($activeBranch);
    $context->setRoleAssignment($assignment->load('role'));

    $component = Livewire::test(ListOrganisationUserRoles::class);
    $options = $component->instance()
        ->getTable()
        ->getFilter('branch_id')
        ?->getOptions();

    expect($options)->toBe([$activeBranch->id => $activeBranch->name])
        ->not->toHaveKey($sameOrganisationBranch->id)
        ->not->toHaveKey($foreignBranch->id);
});

test('role assignment global search excludes other contexts and platform roles', function () {
    $actor = User::factory()->create();
    $visibleUser = User::factory()->create(['name' => 'Searchable Officer']);
    $foreignUser = User::factory()->create(['name' => 'Searchable Officer']);
    $platformUser = User::factory()->create(['name' => 'Searchable Officer']);
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $visibleAssignment = filamentAssignment($visibleUser, $organisation, SystemRole::Auditor);
    $foreignAssignment = filamentAssignment($foreignUser, $foreignOrganisation, SystemRole::Auditor);
    $platformAssignment = OrganisationUserRole::factory()
        ->for($platformUser)
        ->for($organisation)
        ->state(['role_id' => Role::findByName(SystemRole::PlatformAdmin->value)->getKey()])
        ->create();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    $results = OrganisationUserRoleResource::getGlobalSearchResults('Searchable Officer');

    expect($results)->toHaveCount(1)
        ->and($results->first()?->url)
        ->toBe(OrganisationUserRoleResource::getUrl('view', ['record' => $visibleAssignment->public_id]))
        ->not->toBe(OrganisationUserRoleResource::getUrl('view', ['record' => $foreignAssignment->public_id]))
        ->not->toBe(OrganisationUserRoleResource::getUrl('view', ['record' => $platformAssignment->public_id]));
});

test('role assignment direct URLs reject foreign UUIDs', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::OrganisationAdmin);
    $foreignAssignment = filamentAssignment(User::factory()->create(), $foreignOrganisation, SystemRole::Auditor);

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(OrganisationUserRoleResource::getUrl('view', ['record' => $foreignAssignment->public_id]))
        ->assertNotFound();
});

test('role assignment direct URLs reject platform administrator assignments', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::OrganisationAdmin);
    $platformAssignment = OrganisationUserRole::factory()
        ->for(User::factory())
        ->for($organisation)
        ->state(['role_id' => Role::findByName(SystemRole::PlatformAdmin->value)->getKey()])
        ->create();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
                'role_assignment_public_id' => $assignment->public_id,
            ],
        ])
        ->get(OrganisationUserRoleResource::getUrl('view', ['record' => $platformAssignment->public_id]))
        ->assertNotFound();
});

test('stage two organisation resources are read only and use public UUID routes', function () {
    expect(OrganisationResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(OrganisationUserResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(OrganisationUserRoleResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(OrganisationResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(OrganisationUserResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(OrganisationUserRoleResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(OrganisationUserRoleResource::canCreate())->toBeFalse();
});

test('stage two navigation and direct URLs require their specific permissions', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($user, $organisation, SystemRole::Treasurer);

    $this->actingAs($user);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    expect(OrganisationResource::canViewAny())->toBeFalse()
        ->and(OrganisationUserResource::canViewAny())->toBeFalse()
        ->and(OrganisationUserRoleResource::canViewAny())->toBeFalse();

    $session = [
        'active_context' => [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => null,
            'role_assignment_public_id' => $assignment->public_id,
        ],
    ];

    $this->withSession($session)->get(OrganisationResource::getUrl('index'))->assertForbidden();
    $this->withSession($session)->get(OrganisationUserResource::getUrl('index'))->assertForbidden();
    $this->withSession($session)->get(OrganisationUserRoleResource::getUrl('index'))->assertForbidden();
});

test('organisation administrators grant audited access through the domain action', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['email' => 'private-target@example.test']);
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListOrganisationUsers::class)
        ->assertActionVisible(TestAction::make('grantAccess')->table())
        ->callAction(TestAction::make('grantAccess')->table(), data: [
            'user_uuid' => $target->public_id,
            'status' => 'active',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Organisation access granted')
        ->assertDontSee('private-target@example.test');

    $access = OrganisationUser::query()
        ->whereBelongsTo($organisation)
        ->whereBelongsTo($target)
        ->firstOrFail();

    expect($access->status->value)->toBe('active')
        ->and($access->joined_at)->not->toBeNull()
        ->and($access->granted_by_user_id)->toBe($actor->id);
});

test('organisation access management is hidden without the management permission', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager, $branch);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load('role'));

    Livewire::test(ListOrganisationUsers::class)
        ->assertActionHidden(TestAction::make('grantAccess')->table());
});

test('organisation administrators manage access status through the audited workflow', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $targetAccess = OrganisationUser::factory()->for($target)->for($organisation)->create();
    $actorAccess = OrganisationUser::query()
        ->whereBelongsTo($organisation)
        ->whereBelongsTo($actor)
        ->firstOrFail();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    Livewire::test(ListOrganisationUsers::class)
        ->assertActionVisible(TestAction::make('suspend')->table($targetAccess))
        ->assertActionHidden(TestAction::make('suspend')->table($actorAccess))
        ->callAction(TestAction::make('suspend')->table($targetAccess), data: [
            'reason' => 'Access paused while officer duties are reviewed.',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Organisation access updated');

    expect($targetAccess->refresh()->status)->toBe(
        OrganisationAccessStatus::Suspended,
    )->and($targetAccess->statusHistories()->sole()->changed_by_user_id)->toBe($actor->id);

    Livewire::test(ListOrganisationUsers::class)
        ->assertActionVisible(TestAction::make('reactivate')->table($targetAccess))
        ->callAction(TestAction::make('reactivate')->table($targetAccess))
        ->assertNotified('Organisation access updated');

    expect($targetAccess->refresh()->status)->toBe(
        OrganisationAccessStatus::Active,
    )->and($targetAccess->statusHistories()->count())->toBe(2);
});

test('role assignment form delegates to AssignRoleToUser and excludes platform roles', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $contextAssignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    $role = Role::findByName(SystemRole::MembershipOfficer->value);
    $expectedAssignment = OrganisationUserRole::factory()
        ->for($target)
        ->for($organisation)
        ->state([
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ])
        ->make();

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($contextAssignment->load(['role', 'user']));

    $assignRole = Mockery::mock(AssignRoleToUser::class);
    $assignRole->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            User $assigningUser,
            Organisation $selectedOrganisation,
            User $targetUser,
            Role $selectedRole,
            ?Branch $selectedBranch,
        ): bool => $assigningUser->is($actor)
            && $selectedOrganisation->is($organisation)
            && $targetUser->is($target)
            && $selectedRole->is($role)
            && $selectedBranch?->is($branch) === true)
        ->andReturn($expectedAssignment);
    app()->instance(AssignRoleToUser::class, $assignRole);

    Livewire::test(ListOrganisationUserRoles::class)
        ->assertActionVisible(TestAction::make('assignRole')->table())
        ->callAction(TestAction::make('assignRole')->table(), data: [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'starts_at' => null,
            'ends_at' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Role assigned');

    $platformRole = Role::findByName(SystemRole::PlatformAdmin->value);

    Livewire::test(ListOrganisationUserRoles::class)
        ->callAction(TestAction::make('assignRole')->table(), data: [
            'user_id' => $target->id,
            'role_id' => $platformRole->id,
            'branch_id' => $branch->id,
        ])
        ->assertNotNotified('Role assigned');
});

test('role assignment form rejects foreign users branches and platform roles', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $foreignTarget = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $foreignBranch = Branch::factory()->for($foreignOrganisation)->create();
    $contextAssignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    OrganisationUser::factory()->for($foreignTarget)->for($foreignOrganisation)->create();
    $role = Role::findByName(SystemRole::MembershipOfficer->value);
    $platformRole = Role::findByName(SystemRole::PlatformAdmin->value);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($contextAssignment->load(['role', 'user']));

    Livewire::test(ListOrganisationUserRoles::class)
        ->callAction(TestAction::make('assignRole')->table(), data: [
            'user_id' => $foreignTarget->id,
            'role_id' => $role->id,
            'branch_id' => $branch->id,
        ])
        ->assertHasFormErrors(['user_id'])
        ->assertNotNotified('Role assigned');

    Livewire::test(ListOrganisationUserRoles::class)
        ->callAction(TestAction::make('assignRole')->table(), data: [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'branch_id' => $foreignBranch->id,
        ])
        ->assertHasFormErrors(['branch_id'])
        ->assertNotNotified('Role assigned');

    Livewire::test(ListOrganisationUserRoles::class)
        ->callAction(TestAction::make('assignRole')->table(), data: [
            'user_id' => $target->id,
            'role_id' => $platformRole->id,
            'branch_id' => $branch->id,
        ])
        ->assertHasFormErrors(['role_id'])
        ->assertNotNotified('Role assigned');
});

test('organisation administrators revoke roles through the audited domain workflow', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $contextAssignment = filamentAssignment($actor, $organisation, SystemRole::OrganisationAdmin);
    $targetAssignment = filamentAssignment($target, $organisation, SystemRole::Auditor);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($contextAssignment->load(['role', 'user']));

    Livewire::test(ListOrganisationUserRoles::class)
        ->assertActionVisible(TestAction::make('revoke')->table($targetAssignment))
        ->assertActionHidden(TestAction::make('revoke')->table($contextAssignment))
        ->callAction(TestAction::make('revoke')->table($targetAssignment), data: [
            'reason' => 'Duties transferred to another officer.',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Role assignment revoked');

    expect($targetAssignment->refresh()->status->value)->toBe('revoked')
        ->and($targetAssignment->revoked_by_user_id)->toBe($actor->id)
        ->and($targetAssignment->revoked_at)->not->toBeNull()
        ->and($targetAssignment->revocation_reason)->toBe('Duties transferred to another officer.');
});

test('role mutation actions are hidden without assignment permissions', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $contextAssignment = filamentAssignment($actor, $organisation, SystemRole::BranchManager, $branch);
    $targetAssignment = filamentAssignment($target, $organisation, SystemRole::MembershipOfficer, $branch);

    $this->actingAs($actor);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($contextAssignment->load('role'));

    Livewire::test(ListOrganisationUserRoles::class)
        ->assertActionHidden(TestAction::make('assignRole')->table())
        ->assertActionHidden(TestAction::make('revoke')->table($targetAssignment));
});
