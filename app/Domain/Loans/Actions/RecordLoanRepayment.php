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
use App\Domain\Loans\Enums\LoanRepaymentStatus;
use App\Domain\Loans\Enums\LoanSequenceType;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Loans\Services\AllocateLoanRepayment;
use App\Domain\Loans\Services\LoanNumberGenerator;
use App\Domain\Loans\Services\RecalculateLoanPosition;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordLoanRepayment
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private AllocateLoanRepayment $allocator, private PostFinancialTransaction $posting, private LoanNumberGenerator $numbers, private RecalculateLoanPosition $positions, private IssueLoanRepaymentReceipt $receipts) {}

    public function handle(User $actor, Loan $loan, Wallet $source, int $amountMinor, string $method, CarbonImmutable $date, string $idempotencyKey, FoundationPermission $permission = FoundationPermission::LoanRepaymentsCollect): LoanRepayment
    {
        $this->authorization->authorize($permission->value);
        $fingerprint = hash('sha256', implode('|', [$loan->public_id, $source->public_id, $amountMinor, $method, $date->toDateString()]));

        return DB::transaction(function () use ($actor, $loan, $source, $amountMinor, $method, $date, $idempotencyKey, $fingerprint, $permission): LoanRepayment {
            $existing = LoanRepayment::query()->where('organisation_id', $this->context->organisation()->getKey())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidLoanOperation('Idempotency key conflict.');
                }

                return $existing;
            }
            $locked = Loan::query()->with(['product', 'member', 'application.organisation'])->where('organisation_id', $this->context->organisation()->getKey())->lockForUpdate()->whereKey($loan->getKey())->firstOrFail();
            if (! in_array($locked->status, [LoanStatus::Active, LoanStatus::Suspended], true) || $amountMinor <= 0 || $source->organisation_id !== $locked->organisation_id || $source->branch_id !== $locked->branch_id || $source->currency_code !== $locked->currency_code || $source->status !== WalletStatus::Active) {
                throw new InvalidLoanOperation('The repayment cannot be recorded.');
            }
            $memberLoanWallet = $locked->member->wallets()->where('wallet_type', WalletType::MemberLoan)->where('status', WalletStatus::Active)->firstOrFail();
            $publicId = (string) Str::uuid7();
            $repayment = LoanRepayment::query()->create(['public_id' => $publicId, 'organisation_id' => $locked->organisation_id, 'branch_id' => $locked->branch_id, 'loan_id' => $locked->getKey(), 'member_id' => $locked->member_id, 'source_wallet_id' => $source->getKey(), 'repayment_number' => $this->numbers->next($locked->application->organisation, LoanSequenceType::Repayment, $date->year), 'amount_minor' => $amountMinor, 'currency_code' => $locked->currency_code, 'payment_method' => $method, 'repayment_date' => $date, 'collector_user_id' => $actor->getKey(), 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint]);
            $allocated = $this->allocator->allocate($repayment, $locked, $amountMinor, $date);
            $entries = [new TransactionEntryData($source->ledgerAccount, EntryType::Debit, $amountMinor, 'Loan repayment '.$locked->loan_number, null, $source)];
            if ($allocated['principal'] > 0) {
                $entries[] = new TransactionEntryData($memberLoanWallet->ledgerAccount, EntryType::Credit, $allocated['principal'], 'Loan principal repayment', $locked->member, $memberLoanWallet);
            }
            foreach (['interest' => AccountPurpose::LoanInterestIncome, 'fee' => AccountPurpose::LoanFeeIncome, 'penalty' => AccountPurpose::LoanPenaltyIncome] as $component => $purpose) {
                if ($allocated[$component] > 0) {
                    $entries[] = new TransactionEntryData($this->account($purpose), EntryType::Credit, $allocated[$component], 'Loan '.$component, $locked->member);
                }
            }
            if ($allocated['unallocated'] > 0) {
                throw new InvalidLoanOperation('Unallocated loan repayments are not supported.');
            }
            $transaction = $this->posting->handle($actor, new TransactionData($locked->application->branch, FinancialTransactionType::LoanRepayment, $locked->currency_code, $date, 'Loan repayment '.$locked->loan_number, 'loan-repayment:'.$idempotencyKey, null, FinancialReferenceType::Loan, $locked->public_id), $entries, $permission->value);
            $repayment->update(['financial_transaction_id' => $transaction->getKey(), 'principal_allocated_minor' => $allocated['principal'], 'interest_allocated_minor' => $allocated['interest'], 'fee_allocated_minor' => $allocated['fee'], 'penalty_allocated_minor' => $allocated['penalty'], 'unallocated_minor' => 0, 'status' => LoanRepaymentStatus::Allocated]);
            $receipt = $this->receipts->handle($repayment->load(['loan.application.organisation', 'loan.member']), $actor);
            $repayment->update(['receipt_id' => $receipt->getKey()]);
            $this->positions->recalculate($locked, $date);

            return $repayment->refresh()->load(['loan', 'sourceWallet', 'financialTransaction', 'receipt', 'allocations.installment']);
        }, attempts: 3);
    }

    private function account(AccountPurpose $purpose): LedgerAccount
    {
        return LedgerAccount::query()->where('organisation_id', $this->context->organisation()->getKey())->where('purpose', $purpose)->where('status', 'active')->where('allows_direct_posting', true)->first() ?? throw new InvalidLoanOperation('Required loan ledger account is unavailable.');
    }
}
