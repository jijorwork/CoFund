<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Enums\ApprovalDecisionOutcome;
use App\Domain\Approvals\Enums\ApprovalRequestStatus;
use App\Domain\Approvals\Exceptions\InvalidApprovalOperation;
use App\Domain\Approvals\Models\ApprovalDecision;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecideApprovalRequest
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
    ) {}

    public function handle(
        ApprovalRequest $request,
        User $actor,
        ApprovalDecisionOutcome $outcome,
        ?string $reason = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($request, $actor, $outcome, $reason): ApprovalRequest {
            $locked = ApprovalRequest::query()
                ->where('organisation_id', $this->context->organisation()->getKey())
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $step = $locked->current_step_number;
            $permissions = $locked->step_permissions;
            $requiredPermission = $permissions[$step - 1] ?? null;

            if (
                $locked->status !== ApprovalRequestStatus::Pending
                || ! is_string($requiredPermission)
                || $locked->requested_by_user_id === $actor->getKey()
                || $this->context->roleAssignment()->user_id !== $actor->getKey()
                || ($this->context->branch() !== null
                    && $locked->branch_id !== $this->context->branch()->getKey())
                || ($outcome === ApprovalDecisionOutcome::Rejected && trim((string) $reason) === '')
                || $locked->decisions()->where('decided_by_user_id', $actor->getKey())->exists()
            ) {
                throw new InvalidApprovalOperation('The approval decision is not allowed in this context.');
            }

            $this->authorization->authorize($requiredPermission);

            ApprovalDecision::query()->create([
                'organisation_id' => $locked->organisation_id,
                'approval_request_id' => $locked->getKey(),
                'step_number' => $step,
                'outcome' => $outcome,
                'decided_by_user_id' => $actor->getKey(),
                'acting_role_id' => $this->context->role()->getKey(),
                'role_assignment_id' => $this->context->roleAssignment()->getKey(),
                'reason' => $reason,
            ]);

            if ($outcome === ApprovalDecisionOutcome::Rejected || $step === count($permissions)) {
                $locked->update([
                    'status' => $outcome === ApprovalDecisionOutcome::Rejected
                        ? ApprovalRequestStatus::Rejected
                        : ApprovalRequestStatus::Approved,
                    'active_subject_key' => null,
                    'completed_at' => now(),
                ]);
            } else {
                $locked->update(['current_step_number' => $step + 1]);
            }

            return $locked->refresh()->load('decisions');
        }, attempts: 3);
    }
}
