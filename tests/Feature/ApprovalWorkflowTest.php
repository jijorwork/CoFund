<?php

use App\Domain\Approvals\Actions\DecideApprovalRequest;
use App\Domain\Approvals\Actions\SubmitApprovalRequest;
use App\Domain\Approvals\Data\ApprovalDefinition;
use App\Domain\Approvals\Enums\ApprovalDecisionOutcome;
use App\Domain\Approvals\Enums\ApprovalRequestStatus;
use App\Domain\Approvals\Exceptions\InvalidApprovalOperation;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Exceptions\PermissionDenied;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function memberApprovalDefinition(): ApprovalDefinition
{
    return new ApprovalDefinition(
        key: 'member.status-review',
        version: 1,
        submitPermission: FoundationPermission::MembersUpdate,
        stepPermissions: [
            FoundationPermission::MembersSuspend,
            FoundationPermission::MembersSuspend,
        ],
    );
}

test('approval requests snapshot steps and require distinct decision makers', function () {
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $requester = User::factory()->create();
    establishMembershipDomainContext($requester, $organisation);

    $submit = app(SubmitApprovalRequest::class);
    $request = $submit->handle($requester, $member, memberApprovalDefinition());

    expect($request->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($request->step_permissions)->toBe([
            FoundationPermission::MembersSuspend->value,
            FoundationPermission::MembersSuspend->value,
        ])
        ->and($request->subject_public_id)->toBe($member->public_id);

    expect(fn () => $submit->handle($requester, $member, memberApprovalDefinition()))
        ->toThrow(InvalidApprovalOperation::class);
    expect(fn () => app(DecideApprovalRequest::class)->handle(
        $request,
        $requester,
        ApprovalDecisionOutcome::Approved,
    ))->toThrow(InvalidApprovalOperation::class);

    $firstApprover = User::factory()->create();
    establishMembershipDomainContext($firstApprover, $organisation);
    $decide = app(DecideApprovalRequest::class);
    $decide->handle($request, $firstApprover, ApprovalDecisionOutcome::Approved);

    expect($request->refresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($request->current_step_number)->toBe(2)
        ->and($request->decisions()->count())->toBe(1);
    expect(fn () => $decide->handle($request, $firstApprover, ApprovalDecisionOutcome::Approved))
        ->toThrow(InvalidApprovalOperation::class);

    $secondApprover = User::factory()->create();
    establishMembershipDomainContext($secondApprover, $organisation);
    $decide->handle($request, $secondApprover, ApprovalDecisionOutcome::Approved);

    expect($request->refresh()->status)->toBe(ApprovalRequestStatus::Approved)
        ->and($request->active_subject_key)->toBeNull()
        ->and($request->decisions()->pluck('decided_by_user_id')->all())
        ->toBe([$firstApprover->id, $secondApprover->id]);

    $firstDecision = $request->decisions()->firstOrFail();
    expect(fn () => $firstDecision->update(['reason' => 'changed']))
        ->toThrow(LogicException::class);
    expect(fn () => $firstDecision->delete())
        ->toThrow(LogicException::class);

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment(OrganisationUserRole::query()
        ->whereBelongsTo($requester, 'user')
        ->whereBelongsTo($organisation)
        ->firstOrFail());
    expect($submit->handle($requester, $member, memberApprovalDefinition()))
        ->toBeInstanceOf(ApprovalRequest::class);
});

test('approval actions reject foreign subjects and branch contexts', function () {
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $requester = User::factory()->create();
    establishMembershipDomainContext($requester, $organisation, $branch);
    $submit = app(SubmitApprovalRequest::class);

    expect(fn () => $submit->handle(
        $requester,
        Member::factory()->for($otherOrganisation)->create(),
        memberApprovalDefinition(),
    ))->toThrow(InvalidApprovalOperation::class);
    expect(fn () => $submit->handle(
        $requester,
        Member::factory()->for($organisation)->for($otherBranch)->create(),
        memberApprovalDefinition(),
    ))->toThrow(InvalidApprovalOperation::class);

    $member = Member::factory()->for($organisation)->for($branch)->create();
    $request = $submit->handle($requester, $member, memberApprovalDefinition());
    $approver = User::factory()->create();
    establishMembershipDomainContext($approver, $otherOrganisation);

    expect(fn () => app(DecideApprovalRequest::class)->handle(
        $request,
        $approver,
        ApprovalDecisionOutcome::Approved,
    ))->toThrow(ModelNotFoundException::class);
    expect($request->decisions()->count())->toBe(0);
});

test('rejection requires a reason and closes the approval request', function () {
    $organisation = Organisation::factory()->create();
    $member = Member::factory()->for($organisation)->create();
    $requester = User::factory()->create();
    establishMembershipDomainContext($requester, $organisation);
    $request = app(SubmitApprovalRequest::class)->handle(
        $requester,
        $member,
        memberApprovalDefinition(),
    );

    $approver = User::factory()->create();
    establishMembershipDomainContext($approver, $organisation);
    $decide = app(DecideApprovalRequest::class);

    expect(fn () => $decide->handle($request, $approver, ApprovalDecisionOutcome::Rejected, ' '))
        ->toThrow(InvalidApprovalOperation::class);

    $decide->handle($request, $approver, ApprovalDecisionOutcome::Rejected, 'Insufficient evidence');

    expect($request->refresh()->status)->toBe(ApprovalRequestStatus::Rejected)
        ->and($request->decisions()->sole()->reason)->toBe('Insufficient evidence')
        ->and($request->active_subject_key)->toBeNull();
});

test('approval decisions require the active branch and the step permission', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    $requester = User::factory()->create();
    establishMembershipDomainContext($requester, $organisation, $branch);
    $request = app(SubmitApprovalRequest::class)->handle(
        $requester,
        $member,
        memberApprovalDefinition(),
    );

    $approver = User::factory()->create();
    establishMembershipDomainContext($approver, $organisation, $otherBranch);

    expect(fn () => app(DecideApprovalRequest::class)->handle(
        $request,
        $approver,
        ApprovalDecisionOutcome::Approved,
    ))->toThrow(InvalidApprovalOperation::class);

    $role = app(TenantContext::class)->role();
    $role->revokePermissionTo(FoundationPermission::MembersSuspend->value);
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment(OrganisationUserRole::factory()
        ->for($approver)
        ->for($organisation)
        ->state(['branch_id' => $branch->id, 'role_id' => $role->id])
        ->create());

    expect(fn () => app(DecideApprovalRequest::class)->handle(
        $request,
        $approver,
        ApprovalDecisionOutcome::Approved,
    ))->toThrow(PermissionDenied::class);
    expect($request->decisions()->count())->toBe(0);
});
