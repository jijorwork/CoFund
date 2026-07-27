<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Enums\CashDepositMethod;
use App\Domain\Reconciliation\Enums\CashDepositStatus;
use App\Domain\Reconciliation\Enums\ReconciliationSequenceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CashDepositBatch;
use App\Domain\Reconciliation\Services\ReconciliationNumberGenerator;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManageCashDeposit
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private ReconciliationNumberGenerator $numbers,
        private CalculateWalletBalance $balances,
        private PostFinancialTransaction $posting,
        private ReverseFinancialTransaction $reversals,
    ) {}

    public function create(
        User $actor,
        Wallet $source,
        Wallet $destination,
        CashDepositMethod $method,
        CarbonImmutable $date,
        int $amountMinor,
        string $idempotencyKey,
        ?string $reference = null,
        ?string $notes = null,
    ): CashDepositBatch {
        $this->authorization->authorize(FoundationPermission::CashDepositsCreate->value);
        $this->scope->assertBranch($source->branch_id);
        $fingerprint = hash('sha256', implode('|', [$source->public_id, $destination->public_id, $method->value, $date->toDateString(), $amountMinor, $reference]));

        return DB::transaction(function () use ($actor, $source, $destination, $method, $date, $amountMinor, $idempotencyKey, $reference, $notes, $fingerprint): CashDepositBatch {
            $existing = CashDepositBatch::query()->whereBelongsTo($this->context->organisation())
                ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidReconciliationOperation('The idempotency key was reused with a different payload.');
                }

                return $existing;
            }

            $destinationType = match ($method) {
                CashDepositMethod::BankCashDeposit, CashDepositMethod::BankTransfer => WalletType::Bank,
                CashDepositMethod::MobileMoneyTransfer => WalletType::MobileMoney,
                CashDepositMethod::InternalCashTransfer => throw new InvalidReconciliationOperation('Internal cash transfer is outside the deposit workflow.'),
            };
            if ($amountMinor <= 0 || trim($idempotencyKey) === ''
                || $source->organisation_id !== $this->context->organisation()->getKey()
                || $destination->organisation_id !== $source->organisation_id
                || $source->branch_id !== $destination->branch_id
                || $source->wallet_type !== WalletType::OfficeCash
                || $destination->wallet_type !== $destinationType
                || $source->status !== WalletStatus::Active || $destination->status !== WalletStatus::Active
                || $source->currency_code !== $destination->currency_code) {
                throw new InvalidReconciliationOperation('The cash deposit context is invalid.');
            }

            return CashDepositBatch::query()->create([
                'organisation_id' => $source->organisation_id,
                'branch_id' => $source->branch_id,
                'deposit_number' => $this->numbers->next($source->organisation, ReconciliationSequenceType::Deposit, $date->year),
                'source_wallet_id' => $source->getKey(),
                'destination_wallet_id' => $destination->getKey(),
                'deposit_method' => $method,
                'deposit_date' => $date->toDateString(),
                'amount_minor' => $amountMinor,
                'currency_code' => $source->currency_code,
                'status' => CashDepositStatus::Draft,
                'bank_reference' => in_array($method, [CashDepositMethod::BankCashDeposit, CashDepositMethod::BankTransfer], true) ? $reference : null,
                'mobile_money_reference' => $method === CashDepositMethod::MobileMoneyTransfer ? $reference : null,
                'deposited_by_user_id' => $actor->getKey(),
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
            ]);
        }, attempts: 3);
    }

    public function submit(User $actor, CashDepositBatch $deposit): CashDepositBatch
    {
        $this->authorization->authorize(FoundationPermission::CashDepositsCreate->value);
        if ($deposit->status !== CashDepositStatus::Draft || $deposit->deposited_by_user_id !== $actor->getKey()) {
            throw new InvalidReconciliationOperation('Only the depositor can submit a draft.');
        }
        $deposit->update(['status' => CashDepositStatus::Submitted]);

        return $deposit->refresh();
    }

    public function confirm(User $actor, CashDepositBatch $deposit): CashDepositBatch
    {
        $this->authorization->authorize(FoundationPermission::CashDepositsConfirm->value);

        return DB::transaction(function () use ($actor, $deposit): CashDepositBatch {
            $locked = CashDepositBatch::query()->with(['sourceWallet.ledgerAccount', 'destinationWallet.ledgerAccount', 'branch'])
                ->lockForUpdate()->whereKey($deposit->getKey())->firstOrFail();
            if ($locked->status !== CashDepositStatus::Submitted || $locked->deposited_by_user_id === $actor->getKey()) {
                throw new InvalidReconciliationOperation('Deposit confirmation requires an independent actor.');
            }
            if ($this->balances->calculate($locked->sourceWallet)->displayBalanceMinor < $locked->amount_minor) {
                throw new InvalidReconciliationOperation('The office cash wallet has insufficient funds.');
            }

            $transaction = $this->posting->handle($actor, new TransactionData(
                branch: $locked->branch,
                transactionType: FinancialTransactionType::CashDeposit,
                currencyCode: $locked->currency_code,
                transactionDate: CarbonImmutable::parse($locked->deposit_date),
                description: 'Cash deposit '.$locked->deposit_number,
                idempotencyKey: 'cash-deposit:'.$locked->idempotency_key,
                externalReference: $locked->bank_reference ?? $locked->mobile_money_reference,
                referenceType: FinancialReferenceType::CashDeposit,
                referenceId: $locked->public_id,
            ), [
                new TransactionEntryData($locked->destinationWallet->ledgerAccount, EntryType::Debit, $locked->amount_minor, 'Cash deposited', null, $locked->destinationWallet),
                new TransactionEntryData($locked->sourceWallet->ledgerAccount, EntryType::Credit, $locked->amount_minor, 'Office cash deposited', null, $locked->sourceWallet),
            ], FoundationPermission::CashDepositsConfirm->value);
            $locked->update(['status' => CashDepositStatus::Confirmed, 'confirmed_by_user_id' => $actor->getKey(), 'confirmed_at' => now(), 'financial_transaction_id' => $transaction->getKey()]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, CashDepositBatch $deposit, string $reason): CashDepositBatch
    {
        $this->authorization->authorize(FoundationPermission::CashDepositsReject->value);
        if ($deposit->status !== CashDepositStatus::Submitted || $deposit->deposited_by_user_id === $actor->getKey() || trim($reason) === '') {
            throw new InvalidReconciliationOperation('The deposit cannot be rejected.');
        }
        $deposit->update(['status' => CashDepositStatus::Rejected, 'notes' => trim(($deposit->notes ? $deposit->notes."\n" : '').'Rejected: '.$reason)]);

        return $deposit->refresh();
    }

    public function reverse(User $actor, CashDepositBatch $deposit, string $reason, string $idempotencyKey): CashDepositBatch
    {
        $this->authorization->authorize(FoundationPermission::CashDepositsReverse->value);
        if ($deposit->status !== CashDepositStatus::Confirmed || $deposit->confirmed_by_user_id === $actor->getKey()) {
            throw new InvalidReconciliationOperation('The confirmed deposit requires independent reversal.');
        }
        $this->reversals->handle($deposit->financialTransaction, $actor, $reason, 'cash-deposit-reversal:'.$idempotencyKey, FoundationPermission::CashDepositsReverse->value);
        $deposit->update(['status' => CashDepositStatus::Reversed, 'reversal_reason' => $reason]);

        return $deposit->refresh();
    }
}
