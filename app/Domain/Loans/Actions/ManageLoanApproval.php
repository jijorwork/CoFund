<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\CollateralStatus;
use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanApproval;
use App\Domain\Loans\Services\EvaluateGuarantorEligibility;
use App\Domain\Loans\Services\EvaluateLoanEligibility;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageLoanApproval
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private EvaluateLoanEligibility $eligibility, private EvaluateGuarantorEligibility $guarantorEligibility) {}

    public function decide(User $actor, LoanApplication $application, string $level, bool $approved, int $principalMinor, int $term, ?string $reason = null): LoanApplication
    {
        $permission = match ($level) {
            'review', 'credit_review' => FoundationPermission::LoanApplicationsReview, 'secondary_approval', 'final_approval' => FoundationPermission::LoanApplicationsSecondaryApprove, default => FoundationPermission::LoanApplicationsApprove
        };
        $this->authorization->authorize($approved ? $permission->value : FoundationPermission::LoanApplicationsReject->value);

        return DB::transaction(function () use ($actor, $application, $level, $approved, $principalMinor, $term, $reason): LoanApplication {
            $locked = LoanApplication::query()->with(['product', 'member', 'guarantors.guarantor', 'collateral', 'approvals'])->whereBelongsTo($this->context->organisation())->lockForUpdate()->whereKey($application->getKey())->firstOrFail();
            if (! in_array($locked->workflow_status, [LoanApplicationStatus::Submitted, LoanApplicationStatus::UnderReview, LoanApplicationStatus::PendingApproval], true) || $locked->submitted_by_user_id === $actor->getKey() || $locked->approvals->contains('decided_by_user_id', $actor->getKey())) {
                throw new InvalidLoanOperation('Separation of duties requires a distinct actor.');
            }
            if (! $approved && trim((string) $reason) === '') {
                throw new InvalidLoanOperation('Rejection requires a reason.');
            }
            $eligibility = $this->eligibility->evaluate($locked->member, $locked->product, $principalMinor, $term);
            if ($approved && ! $eligibility['eligible']) {
                throw new InvalidLoanOperation('Eligibility must pass again at approval.');
            }
            if ($approved) {
                $accepted = $locked->guarantors->where('status', GuarantorStatus::Accepted);
                if ($accepted->count() < $locked->product->required_guarantor_count) {
                    throw new InvalidLoanOperation('Required guarantees have not been accepted.');
                }
                foreach ($accepted as $guarantee) {
                    if (! $this->guarantorEligibility->evaluate($guarantee->guarantor, $locked, $guarantee->guaranteed_amount_minor)['eligible']) {
                        throw new InvalidLoanOperation('A guarantor is no longer eligible.');
                    }
                }
                if ($locked->product->requires_collateral && ! $locked->collateral->contains('status', CollateralStatus::Accepted)) {
                    throw new InvalidLoanOperation('Accepted collateral is required.');
                }
            }
            LoanApproval::query()->create(['organisation_id' => $locked->organisation_id, 'loan_application_id' => $locked->getKey(), 'approval_level' => $level, 'decision' => $approved ? 'approved' : 'rejected', 'requested_at' => $locked->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->context->role()->getKey(), 'approved_principal_minor' => $approved ? $principalMinor : null, 'approved_term' => $approved ? $term : null, 'recommended_rate_basis_points' => $approved ? $locked->product->interest_rate_basis_points : null, 'reason' => $reason, 'sequence_number' => $locked->approvals->count() + 1]);
            if (! $approved) {
                $locked->update(['workflow_status' => LoanApplicationStatus::Rejected, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            } elseif (in_array($level, ['review', 'credit_review'], true)) {
                $locked->update(['workflow_status' => LoanApplicationStatus::PendingApproval, 'reviewed_by_user_id' => $actor->getKey(), 'reviewed_at' => now()]);
            } else {
                $secondaryRequired = $locked->product->secondary_approval_threshold_minor !== null && $principalMinor >= $locked->product->secondary_approval_threshold_minor;
                $complete = in_array($level, ['secondary_approval', 'final_approval'], true) || ! $secondaryRequired;
                $locked->update(['workflow_status' => $complete ? LoanApplicationStatus::Approved : LoanApplicationStatus::PendingApproval, 'approved_by_user_id' => $actor->getKey(), 'approved_at' => $complete ? now() : null]);
            }

            return $locked->refresh();
        }, attempts: 3);
    }
}
