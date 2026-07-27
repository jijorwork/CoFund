<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReverseFinancialTransaction
{
    public function __construct(
        private TenantContext $context,
        private AccountingScope $scope,
        private PostFinancialTransaction $post,
    ) {}

    public function handle(
        FinancialTransaction $original,
        User $actor,
        string $reason,
        string $idempotencyKey,
        string $permission = FoundationPermission::TransactionsReverse->value,
    ): FinancialTransaction {
        if (trim($reason) === '') {
            throw new InvalidFinancialOperation('A reversal reason is required.');
        }

        return DB::transaction(function () use ($original, $actor, $reason, $idempotencyKey, $permission): FinancialTransaction {
            $locked = FinancialTransaction::query()
                ->with(['organisation', 'branch', 'entries.ledgerAccount', 'entries.wallet', 'entries.member'])
                ->whereBelongsTo($this->context->organisation())
                ->lockForUpdate()
                ->whereKey($original->getKey())
                ->firstOrFail();
            $this->scope->assertBranch($locked->branch, allowOrganisationWide: true);

            if (
                $locked->status !== FinancialTransactionStatus::Posted
                || $locked->original_transaction_id !== null
                || $locked->reversal_transaction_id !== null
            ) {
                throw new InvalidFinancialOperation('The transaction cannot be reversed.');
            }

            $entries = $locked->entries->map(
                fn ($entry): TransactionEntryData => new TransactionEntryData(
                    ledgerAccount: $entry->ledgerAccount,
                    entryType: $entry->entry_type === EntryType::Debit
                        ? EntryType::Credit
                        : EntryType::Debit,
                    amountMinor: $entry->amount_minor,
                    description: 'Reversal: '.$entry->description,
                    member: $entry->member,
                    wallet: $entry->wallet,
                ),
            )->values()->all();
            $today = CarbonImmutable::now($locked->organisation->timezone)->startOfDay();
            $reversal = $this->post->handle(
                $actor,
                new TransactionData(
                    branch: $locked->branch,
                    transactionType: FinancialTransactionType::Reversal,
                    currencyCode: $locked->currency_code,
                    transactionDate: $today,
                    description: 'Reversal of '.$locked->transaction_number.': '.$reason,
                    idempotencyKey: $idempotencyKey,
                    referenceType: FinancialReferenceType::Transaction,
                    referenceId: $locked->public_id,
                    metadata: ['original_transaction_uuid' => $locked->public_id],
                ),
                array_values($entries),
                $permission,
                $locked,
            );

            $locked->update([
                'status' => FinancialTransactionStatus::Reversed,
                'reversal_transaction_id' => $reversal->getKey(),
                'reversal_reason' => $reason,
                'reversed_by_user_id' => $actor->getKey(),
            ]);

            return $reversal->refresh()->load([
                'branch', 'entries.ledgerAccount', 'entries.wallet',
                'entries.member', 'originalTransaction',
            ]);
        });
    }
}
