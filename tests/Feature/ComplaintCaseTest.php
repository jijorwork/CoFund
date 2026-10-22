<?php

use App\Domain\Complaints\Actions\ManageComplaintCase;
use App\Domain\Complaints\Enums\ComplaintStatus;
use App\Domain\Complaints\Exceptions\InvalidComplaintOperation;
use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Complaints\Models\ComplaintMessage;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\ComplaintDemoSeeder;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function setComplaintContext(User $actor, Organisation $organisation, SystemRole $role, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()->for($actor)->for($organisation)->state([
        'role_id' => Role::findByName($role->value)->getKey(),
        'branch_id' => $branch?->getKey(),
    ])->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment);

    return $assignment;
}

test('complaint cases and append-only correspondence retain tenant references', function () {
    $organisation = Organisation::factory()->create();
    $case = ComplaintCase::factory()->for($organisation)->create();
    $message = ComplaintMessage::factory()->forCase($case)->create();

    expect($case->status)->toBe(ComplaintStatus::Open)
        ->and($message->organisation_id)->toBe($organisation->id)
        ->and($case->messages()->sole()->public_id)->toBe($message->public_id);
    expect(fn () => $message->update(['body' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $message->delete())->toThrow(LogicException::class);
});

test('complaint demo seeder creates local example correspondence', function () {
    $this->seed(ComplaintDemoSeeder::class);

    expect(ComplaintCase::query()->count())->toBe(1)
        ->and(ComplaintMessage::query()->count())->toBe(1)
        ->and(ComplaintCase::query()->sole()->messages()->sole()->visibility)->toBe('complainant');
});

test('staff can open, respond, assign, resolve, close, and reopen a complaint with an audit trail', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    $manager = User::factory()->create();
    setComplaintContext($manager, $organisation, SystemRole::BranchManager, $branch);
    $action = app(ManageComplaintCase::class);

    $case = $action->open($manager, $member, 'Collection concern', 'collection', 'I need a review.');
    expect($case->status)->toBe(ComplaintStatus::Open)
        ->and($case->branch_id)->toBe($branch->id)
        ->and($case->messages()->sole()->visibility)->toBe('complainant');

    $assignee = User::factory()->create();
    OrganisationUserRole::factory()->for($assignee)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::MembershipOfficer->value)->getKey(),
        'branch_id' => $branch->id,
    ])->create();
    $action->assign($case, $manager, $assignee);
    $action->respond($case, $manager, 'We are checking the record.', 'internal');
    $action->resolve($case, $manager, 'The collection record was corrected.');
    $action->close($case, $manager);

    expect($case->refresh()->status)->toBe(ComplaintStatus::Closed)
        ->and($case->assigned_to_user_id)->toBe($assignee->id)
        ->and($case->messages()->count())->toBe(2)
        ->and(AuditEvent::query()->where('subject_public_id', $case->public_id)->count())->toBe(5);
    expect(fn () => $action->respond($case, $manager, 'Too late', 'internal'))
        ->toThrow(InvalidComplaintOperation::class);
    expect(fn () => $action->assign($case, $manager, $assignee))
        ->toThrow(InvalidComplaintOperation::class);

    $action->reopen($case, $manager);
    expect($case->refresh()->status)->toBe(ComplaintStatus::InReview)
        ->and($case->resolution_summary)->toBeNull()
        ->and($case->closed_at)->toBeNull();
});

test('complaint actions reject foreign members, branches, assignees, and actors', function () {
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $manager = User::factory()->create();
    setComplaintContext($manager, $organisation, SystemRole::BranchManager, $branch);
    $action = app(ManageComplaintCase::class);

    expect(fn () => $action->open($manager, Member::factory()->for($otherOrganisation)->create(), 'Foreign', 'service', 'Body'))
        ->toThrow(InvalidComplaintOperation::class);
    expect(fn () => $action->open($manager, Member::factory()->for($organisation)->for($otherBranch)->create(), 'Other branch', 'service', 'Body'))
        ->toThrow(InvalidComplaintOperation::class);
    expect(fn () => $action->open(User::factory()->create(), null, 'Wrong actor', 'service', 'Body'))
        ->toThrow(InvalidComplaintOperation::class);

    $case = $action->open($manager, null, 'Valid', 'service', 'Body');
    $foreignAssignee = User::factory()->create();
    OrganisationUserRole::factory()->for($foreignAssignee)->for($otherOrganisation)->state([
        'role_id' => Role::findByName(SystemRole::MembershipOfficer->value)->getKey(),
    ])->create();
    expect(fn () => $action->assign($case, $manager, $foreignAssignee))->toThrow(InvalidComplaintOperation::class);
    expect($case->refresh()->status)->toBe(ComplaintStatus::Open);
});
