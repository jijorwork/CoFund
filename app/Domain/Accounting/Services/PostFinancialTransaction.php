<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\IdempotencyStatus;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\FinancialIdempotencyKey;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PostFinancialTransaction
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private FinancialPostingFingerprint $fingerprints,
        private TransactionNumberGenerator $numbers,
        private FindAccountingPeriodForDate $periods,
    ) {}

    /**
     * @param  list<TransactionEntryData>  $entries
     */
    public function handle(
        User $actor,
        TransactionData $data,
        array $entries,
        string $permission = FoundationPermission::TransactionsPost->value,
        ?FinancialTransaction $originalTransaction = null,
    ): FinancialTransaction {
        $this->authorization->authorize($permission);
        $organisation = $this->context->organisation();
        $fingerprint = $this->fingerprints->make((int) $organisation->getKey(), $data, $entries);
        $this->validate($data, $entries);

        return DB::transaction(function () use (
            $actor,
            $data,
            $entries,
            $organisation,
            $fingerprint,
            $originalTransaction,
        ): FinancialTransaction {
            $idempotency = $this->lockedIdempotencyKey(
                (int) $organisation->getKey(),
                $data->idempotencyKey,
                $fingerprint,
            );

            if ($idempotency->status === IdempotencyStatus::Completed) {
                if ($idempotency->request_fingerprint !== $fingerprint) {
                    throw new InvalidFinancialOperation('The idempotency key was reused with a different payload.');
                }

                return $idempotency->transaction()
                    ->with($this->transactionRelations())
                    ->firstOrFail();
            }

            if (! $idempotency->wasRecentlyCreated) {
                throw new InvalidFinancialOperation('The idempotent posting is already in progress.');
            }

            $transaction = FinancialTransaction::query()->create([
                'organisation_id' => $organisation->getKey(),
                'branch_id' => $data->branch?->getKey(),
                'transaction_number' => $this->numbers->next(
                    $organisation,
                    $data->transactionDate->year,
                ),
                'transaction_type' => $data->transactionType,
                'status' => FinancialTransactionStatus::Draft,
                'currency_code' => strtoupper($data->currencyCode),
                'transaction_date' => $data->transactionDate->toDateString(),
                'description' => $data->description,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'external_reference' => $data->externalReference,
                'idempotency_key' => $data->idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'original_transaction_id' => $originalTransaction?->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'metadata' => $data->metadata,
            ]);

            foreach ($entries as $index => $entry) {
                TransactionEntry::query()->create([
                    'organisation_id' => $organisation->getKey(),
                    'financial_transaction_id' => $transaction->getKey(),
                    'ledger_account_id' => $entry->ledgerAccount->getKey(),
                    'entry_type' => $entry->entryType,
                    'amount_minor' => $entry->amountMinor,
                    'currency_code' => strtoupper($data->currencyCode),
                    'description' => $entry->description,
                    'member_id' => $entry->member?->getKey(),
                    'wallet_id' => $entry->wallet?->getKey(),
                    'sequence_number' => $index + 1,
                ]);
            }

            $transaction->update([
                'status' => FinancialTransactionStatus::Posted,
                'posted_at' => now(),
                'posted_by_user_id' => $actor->getKey(),
            ]);
            $idempotency->update([
                'status' => IdempotencyStatus::Completed,
                'financial_transaction_id' => $transaction->getKey(),
                'completed_at' => now(),
            ]);

            return $transaction->refresh()->load($this->transactionRelations());
        }, attempts: 3);
    }

    /**
     * @param  list<TransactionEntryData>  $entries
     */
    private function validate(TransactionData $data, array $entries): void
    {
        $organisation = $this->context->organisation();
        $currency = strtoupper($data->currencyCode);

        if (
            trim($data->idempotencyKey) === ''
            || strlen($data->idempotencyKey) > 255
            || trim($data->description) === ''
            || count($entries) < 2
            || $currency !== strtoupper($organisation->currency_code)
            || ($data->branch !== null && $data->branch->organisation_id !== $organisation->getKey())
            || ($this->context->roleAssignment()->branch_id !== null
                && $data->branch?->getKey() !== $this->context->branch()?->getKey())
        ) {
            throw new InvalidFinancialOperation;
        }

        $this->periods->assertPostingAllowed($organisation, $data->transactionDate);
        $debits = 0;
        $credits = 0;

        foreach ($entries as $entry) {
            $account = $entry->ledgerAccount;

            if (
                $entry->amountMinor <= 0
                || $account->organisation_id !== $organisation->getKey()
                || strtoupper($account->currency_code) !== $currency
                || $account->status !== LedgerAccountStatus::Active
                || ! $account->allows_direct_posting
                || $account->normal_balance !== $account->category->normalBalance()
                || ($account->branch_id !== null && $account->branch_id !== $data->branch?->getKey())
            ) {
                throw new InvalidFinancialOperation;
            }

            if ($entry->member !== null && (
                $entry->member->organisation_id !== $organisation->getKey()
                || ($data->branch !== null && $entry->member->branch_id !== $data->branch->getKey())
            )) {
                throw new InvalidFinancialOperation;
            }

            if ($entry->wallet !== null && (
                $entry->wallet->organisation_id !== $organisation->getKey()
                || $entry->wallet->ledger_account_id !== $account->getKey()
                || strtoupper($entry->wallet->currency_code) !== $currency
                || $entry->wallet->status !== WalletStatus::Active
                || ($data->branch !== null && $entry->wallet->branch_id !== $data->branch->getKey())
                || ($entry->member !== null
                    && ($entry->wallet->walletable_type !== 'member'
                        || $entry->wallet->walletable_id !== $entry->member->getKey()))
            )) {
                throw new InvalidFinancialOperation;
            }

            $entry->entryType === EntryType::Debit
                ? $debits += $entry->amountMinor
                : $credits += $entry->amountMinor;
        }

        if ($debits <= 0 || $credits <= 0 || $debits !== $credits) {
            throw new InvalidFinancialOperation('Transaction entries must balance.');
        }
    }

    private function lockedIdempotencyKey(
        int $organisationId,
        string $key,
        string $fingerprint,
    ): FinancialIdempotencyKey {
        $wasRecentlyCreated = false;

        try {
            $record = FinancialIdempotencyKey::query()->firstOrCreate([
                'organisation_id' => $organisationId,
                'idempotency_key' => $key,
            ], [
                'request_fingerprint' => $fingerprint,
                'status' => IdempotencyStatus::Processing,
                'locked_at' => now(),
            ]);
            $wasRecentlyCreated = $record->wasRecentlyCreated;
        } catch (QueryException) {
            // A concurrent request created the organisation-scoped key first.
        }

        $locked = FinancialIdempotencyKey::query()
            ->where('organisation_id', $organisationId)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->firstOrFail();
        $locked->wasRecentlyCreated = $wasRecentlyCreated;

        if ($locked->request_fingerprint !== $fingerprint) {
            throw new InvalidFinancialOperation('The idempotency key was reused with a different payload.');
        }

        return $locked;
    }

    /** @return list<string> */
    private function transactionRelations(): array
    {
        return [
            'branch', 'entries.ledgerAccount', 'entries.wallet',
            'entries.member', 'originalTransaction', 'reversalTransaction',
        ];
    }
}
