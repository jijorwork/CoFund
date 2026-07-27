<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Models\User;

class PostOpeningBalance
{
    public function __construct(private PostFinancialTransaction $post) {}

    /** @param list<TransactionEntryData> $entries */
    public function handle(User $actor, TransactionData $data, array $entries): FinancialTransaction
    {
        if (
            $data->transactionType !== FinancialTransactionType::OpeningBalance
            || ! collect($entries)->contains(
                fn (TransactionEntryData $entry): bool => $entry->ledgerAccount->code === '3000',
            )
        ) {
            throw new InvalidFinancialOperation('Opening balances require the opening-equity account.');
        }

        return $this->post->handle(
            $actor,
            $data,
            $entries,
            FoundationPermission::OpeningBalancesPost->value,
        );
    }
}
