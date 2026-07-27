<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;

class FinancialPostingFingerprint
{
    /** @param list<TransactionEntryData> $entries */
    public function make(int $organisationId, TransactionData $data, array $entries): string
    {
        $canonicalEntries = array_map(
            fn (TransactionEntryData $entry): array => [
                'account' => $entry->ledgerAccount->public_id,
                'wallet' => $entry->wallet?->public_id,
                'member' => $entry->member?->public_id,
                'entry_type' => $entry->entryType->value,
                'amount_minor' => $entry->amountMinor,
                'description' => $entry->description,
            ],
            $entries,
        );

        usort($canonicalEntries, fn (array $left, array $right): int => strcmp(
            json_encode($left, JSON_THROW_ON_ERROR),
            json_encode($right, JSON_THROW_ON_ERROR),
        ));

        return hash('sha256', json_encode([
            'organisation_id' => $organisationId,
            'branch' => $data->branch?->public_id,
            'transaction_type' => $data->transactionType->value,
            'currency_code' => strtoupper($data->currencyCode),
            'transaction_date' => $data->transactionDate->toDateString(),
            'description' => $data->description,
            'external_reference' => $data->externalReference,
            'reference_type' => $data->referenceType?->value,
            'reference_id' => $data->referenceId,
            'entries' => $canonicalEntries,
        ], JSON_THROW_ON_ERROR));
    }
}
