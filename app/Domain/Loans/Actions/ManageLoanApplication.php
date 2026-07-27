<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\EligibilityStatus;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Enums\LoanProductStatus;
use App\Domain\Loans\Enums\LoanSequenceType;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Loans\Services\EvaluateLoanEligibility;
use App\Domain\Loans\Services\LoanNumberGenerator;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManageLoanApplication
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private LoanNumberGenerator $numbers, private EvaluateLoanEligibility $eligibility) {}

    /** @param array<string,mixed> $attributes */
    public function create(User $actor, Member $member, LoanProduct $product, array $attributes): LoanApplication
    {
        $this->authorization->authorize(FoundationPermission::LoanApplicationsCreate->value);
        $organisation = $this->context->organisation();
        if ($member->organisation_id !== $organisation->getKey() || $product->organisation_id !== $organisation->getKey() || $product->status !== LoanProductStatus::Active || $member->branch_id !== $this->context->branch()?->getKey()) {
            throw new InvalidLoanOperation;
        }
        $date = CarbonImmutable::now($organisation->timezone)->startOfDay();

        return DB::transaction(fn (): LoanApplication => LoanApplication::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'branch_id' => $member->branch_id, 'member_id' => $member->getKey(), 'loan_product_id' => $product->getKey(), 'application_number' => $this->numbers->next($organisation, LoanSequenceType::Application, $date->year), 'repayment_frequency' => $product->repayment_frequency, 'application_date' => $date]));
    }

    public function submit(User $actor, LoanApplication $application, bool $override = false, ?string $overrideReason = null): LoanApplication
    {
        $this->authorization->authorize(FoundationPermission::LoanApplicationsSubmit->value);

        return DB::transaction(function () use ($actor, $application, $override, $overrideReason): LoanApplication {
            $locked = LoanApplication::query()->with(['member', 'product'])->whereBelongsTo($this->context->organisation())->lockForUpdate()->whereKey($application->getKey())->firstOrFail();
            if ($locked->workflow_status !== LoanApplicationStatus::Draft) {
                throw new InvalidLoanOperation('Only draft applications can be submitted.');
            }
            $result = $this->eligibility->forApplication($locked);
            if (! $result['eligible']) {
                if (! $override || trim((string) $overrideReason) === '') {
                    throw new InvalidLoanOperation('The application is not eligible.');
                }
                $this->authorization->authorize(FoundationPermission::LoanApplicationsOverrideEligibility->value);
                $result['override_reason'] = $overrideReason;
                $result['override_by_user_id'] = $actor->getKey();
            }
            $locked->update(['eligibility_status' => $result['eligible'] ? (empty($result['warnings']) ? EligibilityStatus::Eligible : EligibilityStatus::EligibleWithWarnings) : EligibilityStatus::Ineligible, 'eligibility_snapshot' => $result, 'product_terms_snapshot' => $locked->product->only(['code', 'currency_code', 'minimum_amount_minor', 'maximum_amount_minor', 'minimum_term', 'maximum_term', 'term_unit', 'repayment_frequency', 'interest_method', 'interest_rate_basis_points', 'grace_period_days', 'processing_fee_type', 'processing_fee_fixed_minor', 'processing_fee_basis_points', 'processing_fee_treatment', 'required_guarantor_count', 'requires_collateral', 'secondary_approval_threshold_minor']), 'workflow_status' => LoanApplicationStatus::Submitted, 'submitted_by_user_id' => $actor->getKey(), 'submitted_at' => now()]);

            return $locked->refresh();
        }, attempts: 3);
    }

    /** @param array<string,mixed> $attributes */
    public function update(LoanApplication $application, array $attributes): LoanApplication
    {
        $this->authorization->authorize(FoundationPermission::LoanApplicationsCreate->value);
        if ($application->organisation_id !== $this->context->organisation()->getKey() || $application->workflow_status !== LoanApplicationStatus::Draft) {
            throw new InvalidLoanOperation('Only draft applications may be updated.');
        }

        $application->update($attributes);

        return $application->refresh();
    }

    public function cancel(User $actor, LoanApplication $application, string $reason): LoanApplication
    {
        $this->authorization->authorize(FoundationPermission::LoanApplicationsCancel->value);
        if (trim($reason) === '' || in_array($application->workflow_status, [LoanApplicationStatus::Disbursed, LoanApplicationStatus::Cancelled, LoanApplicationStatus::Rejected], true)) {
            throw new InvalidLoanOperation;
        }
        $application->update(['workflow_status' => LoanApplicationStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

        return $application->refresh();
    }
}
