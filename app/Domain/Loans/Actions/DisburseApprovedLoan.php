<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\CollateralStatus;
use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Enums\LoanOfferStatus;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanSequenceType;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanDisbursement;
use App\Domain\Loans\Services\EvaluateGuarantorEligibility;
use App\Domain\Loans\Services\EvaluateLoanEligibility;
use App\Domain\Loans\Services\LoanNumberGenerator;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisburseApprovedLoan
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private EvaluateLoanEligibility $eligibility, private EvaluateGuarantorEligibility $guarantors, private CalculateWalletBalance $balances, private CreateMemberWallet $createMemberWallet, private PostFinancialTransaction $posting, private LoanNumberGenerator $numbers) {}

    public function handle(User $actor, Loan $loan, Wallet $source, string $destinationType, string $method, CarbonImmutable $date, string $idempotencyKey, ?string $destinationReference = null): LoanDisbursement
    {
        $this->authorization->authorize(FoundationPermission::LoansDisburse->value);
        $fingerprint = hash('sha256', implode('|', [$loan->public_id, $source->public_id, $destinationType, $method, $date->toDateString(), $destinationReference]));

        return DB::transaction(function () use ($actor, $loan, $source, $destinationType, $method, $date, $idempotencyKey, $destinationReference, $fingerprint): LoanDisbursement {
            $existing = LoanDisbursement::query()->where('organisation_id', $this->context->organisation()->getKey())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidLoanOperation('Idempotency key conflict.');
                }

                return $existing;
            }
            $locked = Loan::query()->with(['application.product', 'application.member', 'application.guarantors.guarantor', 'application.collateral', 'offer', 'product', 'member', 'installments'])->where('organisation_id', $this->context->organisation()->getKey())->lockForUpdate()->whereKey($loan->getKey())->firstOrFail();
            if ($locked->status !== LoanStatus::PendingDisbursement || $locked->application->workflow_status !== LoanApplicationStatus::OfferAccepted || $locked->offer->status !== LoanOfferStatus::Accepted || $locked->offer->expires_at->isPast() || $locked->disbursement()->exists()) {
                throw new InvalidLoanOperation('The loan is not ready for disbursement.');
            }
            if (! $this->eligibility->forApplication($locked->application)['eligible']) {
                throw new InvalidLoanOperation('Eligibility changed before disbursement.');
            }
            $acceptedGuarantees = $locked->application->guarantors->where('status', GuarantorStatus::Accepted);
            if ($acceptedGuarantees->count() < $locked->product->required_guarantor_count) {
                throw new InvalidLoanOperation('Required guarantees are no longer active.');
            }
            foreach ($acceptedGuarantees as $guarantee) {
                if (! $this->guarantors->evaluate($guarantee->guarantor, $locked->application, $guarantee->guaranteed_amount_minor)['eligible']) {
                    throw new InvalidLoanOperation('A guarantor is no longer eligible.');
                }
            }
            if ($locked->product->requires_collateral && ! $locked->application->collateral->contains('status', CollateralStatus::Accepted)) {
                throw new InvalidLoanOperation('Collateral is no longer valid.');
            }
            if ($locked->installments->sum('scheduled_principal_minor') !== $locked->principal_minor + $locked->financed_fee_minor || $locked->installments->sum('scheduled_interest_minor') !== $locked->contractual_interest_minor) {
                throw new InvalidLoanOperation('The repayment schedule does not reconcile.');
            }
            if ($source->organisation_id !== $locked->organisation_id || $source->branch_id !== $locked->branch_id || $source->currency_code !== $locked->currency_code || $source->status !== WalletStatus::Active || $this->balances->calculate($source)->displayBalanceMinor < $locked->offer->principal_minor - $locked->offer->deducted_fee_minor) {
                throw new InvalidLoanOperation('The source wallet cannot fund this disbursement.');
            }
            $memberLoanWallet = $locked->member->wallets()->where('wallet_type', WalletType::MemberLoan)->where('status', WalletStatus::Active)->first()
                ?? $this->createMemberWallet->handle($actor, $locked->member, WalletType::MemberLoan, $locked->member->fullName().' loan', 'LOAN-'.$locked->member->membership_number);
            $receivable = $memberLoanWallet->ledgerAccount;
            $feeAccount = $this->account(AccountPurpose::LoanFeeIncome);
            $entries = [new TransactionEntryData($receivable, EntryType::Debit, $locked->principal_minor + $locked->financed_fee_minor, 'Loan '.$locked->loan_number, $locked->member, $memberLoanWallet), new TransactionEntryData($source->ledgerAccount, EntryType::Credit, $locked->offer->principal_minor - $locked->offer->deducted_fee_minor, 'Loan cash disbursement', null, $source)];
            $fee = $locked->offer->deducted_fee_minor + $locked->offer->financed_fee_minor;
            if ($fee > 0) {
                $entries[] = new TransactionEntryData($feeAccount, EntryType::Credit, $fee, 'Loan processing fee', $locked->member);
            }
            $publicId = (string) Str::uuid7();
            $transaction = $this->posting->handle($actor, new TransactionData($locked->application->branch, FinancialTransactionType::LoanDisbursement, $locked->currency_code, $date, 'Loan disbursement '.$locked->loan_number, 'loan-disbursement:'.$idempotencyKey, null, FinancialReferenceType::Loan, $locked->public_id), $entries, FoundationPermission::LoansDisburse->value);
            $disbursement = LoanDisbursement::query()->create(['public_id' => $publicId, 'organisation_id' => $locked->organisation_id, 'branch_id' => $locked->branch_id, 'loan_id' => $locked->getKey(), 'source_wallet_id' => $source->getKey(), 'member_loan_wallet_id' => $memberLoanWallet->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'disbursement_number' => $this->numbers->next($locked->application->organisation, LoanSequenceType::Disbursement, $date->year), 'destination_type' => $destinationType, 'destination_reference' => $destinationReference, 'gross_principal_minor' => $locked->principal_minor, 'deducted_fee_minor' => $locked->offer->deducted_fee_minor, 'financed_fee_minor' => $locked->financed_fee_minor, 'net_disbursed_minor' => $locked->offer->principal_minor - $locked->offer->deducted_fee_minor, 'currency_code' => $locked->currency_code, 'method' => $method, 'disbursement_date' => $date, 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint, 'disbursed_by_user_id' => $actor->getKey()]);
            $locked->update(['status' => LoanStatus::Active, 'performance_status' => LoanPerformanceStatus::Current, 'disbursed_amount_minor' => $disbursement->net_disbursed_minor, 'disbursement_date' => $date]);
            $locked->application->update(['workflow_status' => LoanApplicationStatus::Disbursed]);

            return $disbursement->load(['loan', 'sourceWallet', 'financialTransaction']);
        }, attempts: 3);
    }

    private function account(AccountPurpose $purpose): LedgerAccount
    {
        return LedgerAccount::query()->where('organisation_id', $this->context->organisation()->getKey())->where('purpose', $purpose)->where('status', 'active')->where('allows_direct_posting', true)->first() ?? throw new InvalidLoanOperation('Required loan ledger account is unavailable.');
    }
}
