<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareVerificationStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;

class ManageWelfareClaimWorkflow
{
    public function __construct(private RoleAuthorization $authorization, private TenantContext $context) {}

    public function review(User $actor, WelfareClaim $claim, ?string $notes = null): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsReview->value);
        if (! in_array($claim->status, [WelfareClaimStatus::Submitted, WelfareClaimStatus::UnderReview, WelfareClaimStatus::PendingVerification], true) || $claim->created_by_user_id === $actor->getKey()) {
            throw new InvalidWelfareOperation('The claim cannot be reviewed by this actor.');
        }
        $claim->approvals()->create(['organisation_id' => $claim->organisation_id, 'approval_level' => 'review', 'decision' => 'reviewed', 'requested_at' => $claim->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->authorizationRoleId(), 'reason' => $notes, 'sequence_number' => $claim->approvals()->count() + 1]);
        $claim->update(['status' => $claim->verification_status === WelfareVerificationStatus::Pending ? WelfareClaimStatus::PendingVerification : WelfareClaimStatus::PendingApproval, 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->getKey()]);

        return $claim->refresh();
    }

    public function verify(User $actor, WelfareClaim $claim, bool $verified, ?string $reason = null): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsVerify->value);
        if ($claim->created_by_user_id === $actor->getKey() || $claim->reviewed_by_user_id === $actor->getKey() || (! $verified && trim((string) $reason) === '')) {
            throw new InvalidWelfareOperation('Verification separation or reason requirements failed.');
        }
        $claim->approvals()->create(['organisation_id' => $claim->organisation_id, 'approval_level' => 'verification', 'decision' => $verified ? 'verified' : 'failed', 'requested_at' => $claim->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->authorizationRoleId(), 'reason' => $reason, 'sequence_number' => $claim->approvals()->count() + 1]);
        $claim->update(['verification_status' => $verified ? WelfareVerificationStatus::Verified : WelfareVerificationStatus::Failed, 'status' => $verified ? WelfareClaimStatus::PendingApproval : WelfareClaimStatus::PendingVerification, 'verified_at' => now(), 'verified_by_user_id' => $actor->getKey()]);

        return $claim->refresh();
    }

    public function reject(User $actor, WelfareClaim $claim, string $reason): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsReject->value);
        if (trim($reason) === '' || $claim->liabilities()->where('status', 'recognised')->exists()) {
            throw new InvalidWelfareOperation('A reason is required and active liabilities must be reversed first.');
        }
        $claim->approvals()->create(['organisation_id' => $claim->organisation_id, 'approval_level' => 'final', 'decision' => 'rejected', 'requested_at' => $claim->submitted_at ?? now(), 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->authorizationRoleId(), 'reason' => $reason, 'sequence_number' => $claim->approvals()->count() + 1]);
        $claim->update(['status' => WelfareClaimStatus::Rejected, 'approval_status' => 'rejected', 'rejected_at' => now(), 'rejected_by_user_id' => $actor->getKey(), 'rejection_reason' => $reason]);

        return $claim->refresh();
    }

    public function cancel(User $actor, WelfareClaim $claim, string $reason): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsCancel->value);
        if (trim($reason) === '' || $claim->liabilities()->where('status', 'recognised')->exists()) {
            throw new InvalidWelfareOperation('A reason is required and active liabilities must be reversed first.');
        }
        $claim->update(['status' => WelfareClaimStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason]);

        return $claim->refresh();
    }

    private function authorizationRoleId(): int
    {
        return $this->context->role()->getKey();
    }
}
