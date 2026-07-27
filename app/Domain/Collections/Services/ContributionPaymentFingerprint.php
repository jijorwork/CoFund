<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Data\ContributionPaymentData;

class ContributionPaymentFingerprint
{
    public function make(ContributionPaymentData $data): string
    {
        $allocations = collect($data->selectedAllocations)
            ->map(fn ($allocation): array => [
                'obligation' => $allocation->obligation->public_id,
                'amount_minor' => $allocation->amountMinor,
            ])
            ->sortBy(['obligation', 'amount_minor'])
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'member' => $data->member->public_id,
            'enrolment' => $data->enrolment?->public_id,
            'plan' => $data->plan?->public_id,
            'amount_minor' => $data->amountMinor,
            'currency_code' => strtoupper($data->currencyCode),
            'payment_method' => $data->paymentMethod->value,
            'payment_date' => $data->paymentDate->toDateString(),
            'source_wallet' => $data->sourceWallet->public_id,
            'destination_wallet' => $data->destinationWallet->public_id,
            'allocation_strategy' => $data->allocationStrategy->value,
            'allocations' => $allocations,
            'external_reference' => $data->externalReference,
            'payer_name' => $data->payerName,
            'payer_phone' => $data->payerPhone,
            'notes' => $data->notes,
        ], JSON_THROW_ON_ERROR));
    }
}
