<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Enums\ExpenseApprovalDecision;
use App\Domain\Expenses\Enums\ExpenseApprovalLevel;
use App\Domain\Expenses\Enums\ExpenseApprovalStatus;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseApproval;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Services\DetermineExpenseApprovalRequirements;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageExpenseApproval
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private DetermineExpenseApprovalRequirements $requirements) {}

    public function submit(User $actor, ExpenseRequest $request): ExpenseRequest
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsSubmit->value);
        if ($request->status !== ExpenseRequestStatus::Draft || $request->requester_user_id !== $actor->getKey()) {
            throw new InvalidExpenseOperation('Only the requester may submit their draft.');
        }
        if ($request->category->requires_receipt && ! $request->documents()->exists()) {
            throw new InvalidExpenseOperation('Supporting evidence is required.');
        }
        $snapshot = $this->requirements->determine($request->category, $request->requested_amount_minor);
        $request->update(['status' => ExpenseRequestStatus::Submitted, 'approval_status' => ExpenseApprovalStatus::Pending, 'approval_requirements' => $snapshot, 'submitted_at' => now()]);

        return $request->refresh();
    }

    public function review(User $actor, ExpenseRequest $request, int $recommendedMinor): ExpenseRequest
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsReview->value);

        return $this->decide($actor, $request, ExpenseApprovalLevel::Review, $recommendedMinor, FoundationPermission::ExpenseRequestsReview);
    }

    public function approve(User $actor, ExpenseRequest $request, int $amountMinor, bool $secondary = false): ExpenseRequest
    {
        $permission = $secondary ? FoundationPermission::ExpenseRequestsSecondaryApprove : FoundationPermission::ExpenseRequestsApprove;
        $this->authorization->authorize($permission->value);

        return $this->decide($actor, $request, $secondary ? ExpenseApprovalLevel::Secondary : ExpenseApprovalLevel::Primary, $amountMinor, $permission);
    }

    private function decide(User $actor, ExpenseRequest $request, ExpenseApprovalLevel $level, int $amountMinor, FoundationPermission $permission): ExpenseRequest
    {
        return DB::transaction(function () use ($actor, $request, $level, $amountMinor): ExpenseRequest {
            $locked = ExpenseRequest::query()->with(['category', 'approvals'])->lockForUpdate()->whereBelongsTo($this->context->organisation())->whereKey($request->getKey())->firstOrFail();
            if (! in_array($locked->status, [ExpenseRequestStatus::Submitted, ExpenseRequestStatus::UnderReview], true) || $locked->requester_user_id === $actor->getKey() || $amountMinor <= 0 || $amountMinor > $locked->requested_amount_minor || $locked->approvals->contains('decided_by_user_id', $actor->getKey())) {
                throw new InvalidExpenseOperation('Approval requires an eligible distinct actor and amount.');
            }
            ExpenseApproval::query()->create(['organisation_id' => $locked->organisation_id, 'expense_request_id' => $locked->getKey(), 'approval_level' => $level, 'decision' => ExpenseApprovalDecision::Approved, 'requested_at' => $locked->submitted_at ?? now(), 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->context->role()->getKey(), 'approved_amount_minor' => $amountMinor, 'sequence_number' => $locked->approvals->count() + 1]);
            if ($level === ExpenseApprovalLevel::Review) {
                $locked->update(['status' => ExpenseRequestStatus::UnderReview, 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->getKey()]);

                return $locked->refresh();
            }
            $needsSecondary = (bool) ($locked->approval_requirements['secondary_required'] ?? false);
            $complete = $level === ExpenseApprovalLevel::Secondary || ! $needsSecondary;
            $locked->update($complete ? ['status' => ExpenseRequestStatus::Approved, 'approval_status' => ExpenseApprovalStatus::Approved, 'approved_amount_minor' => $amountMinor, 'outstanding_amount_minor' => $amountMinor, 'approved_at' => now(), 'approved_by_user_id' => $actor->getKey()] : ['status' => ExpenseRequestStatus::UnderReview, 'approved_amount_minor' => $amountMinor, 'approved_by_user_id' => $actor->getKey()]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, ExpenseRequest $request, string $reason): ExpenseRequest
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsReject->value);
        if ($request->requester_user_id === $actor->getKey() || trim($reason) === '' || ! in_array($request->status, [ExpenseRequestStatus::Submitted, ExpenseRequestStatus::UnderReview], true)) {
            throw new InvalidExpenseOperation;
        }
        ExpenseApproval::query()->create(['organisation_id' => $request->organisation_id, 'expense_request_id' => $request->getKey(), 'approval_level' => ExpenseApprovalLevel::Primary, 'decision' => ExpenseApprovalDecision::Rejected, 'requested_at' => $request->submitted_at ?? now(), 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->context->role()->getKey(), 'reason' => $reason, 'sequence_number' => $request->approvals()->count() + 1]);
        $request->update(['status' => ExpenseRequestStatus::Rejected, 'approval_status' => ExpenseApprovalStatus::Rejected, 'rejected_at' => now(), 'rejected_by_user_id' => $actor->getKey(), 'rejection_reason' => $reason]);

        return $request->refresh();
    }

    public function cancel(User $actor, ExpenseRequest $request, string $reason): ExpenseRequest
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsCancel->value);
        if (! in_array($request->status, [ExpenseRequestStatus::Draft, ExpenseRequestStatus::Submitted, ExpenseRequestStatus::UnderReview, ExpenseRequestStatus::Approved], true) || $request->paid_amount_minor > 0 || trim($reason) === '') {
            throw new InvalidExpenseOperation;
        }
        $request->update(['status' => ExpenseRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason]);

        return $request->refresh();
    }
}
