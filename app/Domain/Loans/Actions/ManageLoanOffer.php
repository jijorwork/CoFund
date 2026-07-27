<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Enums\LoanOfferStatus;
use App\Domain\Loans\Enums\LoanSequenceType;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanOffer;
use App\Domain\Loans\Services\CalculateLoanTerms;
use App\Domain\Loans\Services\GenerateLoanRepaymentSchedule;
use App\Domain\Loans\Services\LoanNumberGenerator;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManageLoanOffer
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateLoanTerms $terms, private LoanNumberGenerator $numbers, private GenerateLoanRepaymentSchedule $schedule) {}

    public function issue(User $actor, LoanApplication $application, CarbonImmutable $firstRepaymentDate, CarbonImmutable $expiresAt): LoanOffer
    {
        $this->authorization->authorize(FoundationPermission::LoanOffersIssue->value);

        return DB::transaction(function () use ($actor, $application, $firstRepaymentDate, $expiresAt): LoanOffer {
            $locked = LoanApplication::query()->with(['product', 'approvals', 'offers'])->whereBelongsTo($this->context->organisation())->lockForUpdate()->whereKey($application->getKey())->firstOrFail();
            if ($locked->workflow_status !== LoanApplicationStatus::Approved) {
                throw new InvalidLoanOperation('Only approved applications may receive offers.');
            }
            $approval = $locked->approvals->where('decision', 'approved')->last();
            if ($approval === null) {
                throw new InvalidLoanOperation;
            }
            $calculated = $this->terms->calculate($locked->product, $approval->approved_principal_minor, $approval->approved_term);
            $maturity = match ($locked->product->repayment_frequency->value) {
                'weekly' => $firstRepaymentDate->addWeeks($approval->approved_term - 1), 'fortnightly' => $firstRepaymentDate->addWeeks(($approval->approved_term - 1) * 2), default => $firstRepaymentDate->addMonthsNoOverflow($approval->approved_term - 1)
            };
            $locked->offers()->where('status', LoanOfferStatus::Issued)->update(['status' => LoanOfferStatus::Superseded]);
            $offer = LoanOffer::query()->create(['organisation_id' => $locked->organisation_id, 'loan_application_id' => $locked->getKey(), 'offer_number' => $this->numbers->next($locked->organisation, LoanSequenceType::Offer, now()->year), 'version' => $locked->offers->count() + 1, 'principal_minor' => $calculated['principal_minor'], 'interest_minor' => $calculated['contractual_interest_minor'], 'fees_minor' => $calculated['processing_fee_minor'], 'financed_fee_minor' => $calculated['financed_fee_minor'], 'deducted_fee_minor' => $calculated['deducted_fee_minor'], 'total_repayable_minor' => $calculated['total_repayable_minor'], 'term' => $approval->approved_term, 'repayment_frequency' => $locked->product->repayment_frequency, 'interest_method' => $locked->product->interest_method, 'interest_rate_basis_points' => $locked->product->interest_rate_basis_points, 'grace_period_days' => $locked->product->grace_period_days, 'first_repayment_date' => $firstRepaymentDate, 'maturity_date' => $maturity, 'issued_at' => now(), 'expires_at' => $expiresAt, 'issued_by_user_id' => $actor->getKey()]);
            $locked->update(['workflow_status' => LoanApplicationStatus::OfferIssued]);

            return $offer;
        }, attempts: 3);
    }

    public function accept(User $actor, LoanOffer $offer): Loan
    {
        $this->authorization->authorize(FoundationPermission::LoanOffersAcceptAssisted->value);

        return DB::transaction(function () use ($actor, $offer): Loan {
            $locked = LoanOffer::query()->with(['application.product', 'application.member', 'application.organisation'])->whereBelongsTo($this->context->organisation())->lockForUpdate()->whereKey($offer->getKey())->firstOrFail();
            if ($locked->status !== LoanOfferStatus::Issued || $locked->expires_at->isPast()) {
                throw new InvalidLoanOperation('The offer is not available for acceptance.');
            }
            $locked->update(['status' => LoanOfferStatus::Accepted, 'accepted_by_user_id' => $actor->getKey(), 'accepted_at' => now()]);
            $application = $locked->application;
            $loan = Loan::query()->create(['organisation_id' => $application->organisation_id, 'branch_id' => $application->branch_id, 'member_id' => $application->member_id, 'loan_product_id' => $application->loan_product_id, 'loan_application_id' => $application->getKey(), 'loan_offer_id' => $locked->getKey(), 'loan_number' => $this->numbers->next($application->organisation, LoanSequenceType::Loan, now()->year), 'principal_minor' => $locked->principal_minor, 'financed_fee_minor' => $locked->financed_fee_minor, 'contractual_interest_minor' => $locked->interest_minor, 'total_repayable_minor' => $locked->total_repayable_minor, 'currency_code' => $application->product->currency_code, 'first_repayment_date' => $locked->first_repayment_date, 'maturity_date' => $locked->maturity_date, 'repayment_frequency' => $locked->repayment_frequency, 'interest_method' => $locked->interest_method, 'interest_rate_basis_points' => $locked->interest_rate_basis_points, 'outstanding_principal_minor' => $locked->principal_minor + $locked->financed_fee_minor, 'outstanding_interest_minor' => $locked->interest_minor]);
            $application->update(['workflow_status' => LoanApplicationStatus::OfferAccepted]);
            $this->schedule->generate($loan->load('offer'));

            return $loan->refresh()->load('installments');
        }, attempts: 3);
    }

    public function decline(User $actor, LoanOffer $offer, string $reason): LoanOffer
    {
        $this->authorization->authorize(FoundationPermission::LoanOffersAcceptAssisted->value);

        return DB::transaction(function () use ($offer, $reason): LoanOffer {
            $locked = LoanOffer::query()
                ->whereBelongsTo($this->context->organisation())
                ->lockForUpdate()
                ->whereKey($offer->getKey())
                ->firstOrFail();

            if ($locked->status !== LoanOfferStatus::Issued || trim($reason) === '') {
                throw new InvalidLoanOperation('Only an issued offer may be declined.');
            }

            $locked->update([
                'status' => LoanOfferStatus::Declined,
                'declined_at' => now(),
                'decline_reason' => $reason,
            ]);
            $locked->application()->update(['workflow_status' => LoanApplicationStatus::Approved]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
