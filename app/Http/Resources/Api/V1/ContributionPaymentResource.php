<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Collections\Models\ContributionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContributionPayment */
class ContributionPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'payment_number' => $this->payment_number,
            'member' => [
                'uuid' => $this->member->public_id,
                'membership_number' => $this->member->membership_number,
                'name' => $this->member->fullName(),
            ],
            'plan' => $this->contributionPlan === null ? null : [
                'uuid' => $this->contributionPlan->public_id,
                'code' => $this->contributionPlan->code,
                'name' => $this->contributionPlan->name,
            ],
            'enrolment' => $this->enrolment === null ? null : ['uuid' => $this->enrolment->public_id],
            'amount_minor' => $this->amount_minor,
            'allocated_amount_minor' => $this->allocated_amount_minor,
            'unallocated_amount_minor' => $this->unallocated_amount_minor,
            'currency_code' => $this->currency_code,
            'payment_method' => $this->payment_method->value,
            'payment_date' => $this->payment_date->toDateString(),
            'received_at' => $this->received_at->toIso8601String(),
            'status' => $this->status->value,
            'receipt' => $this->receipt === null ? null : [
                'uuid' => $this->receipt->public_id,
                'receipt_number' => $this->receipt->receipt_number,
                'status' => $this->receipt->status->value,
            ],
            'collector' => $this->when(
                $this->relationLoaded('collectedBy'),
                fn (): array => ['uuid' => $this->collectedBy->public_id, 'name' => $this->collectedBy->name],
            ),
            'financial_transaction' => $this->when(
                $this->relationLoaded('financialTransaction'),
                fn (): array => [
                    'uuid' => $this->financialTransaction->public_id,
                    'transaction_number' => $this->financialTransaction->transaction_number,
                ],
            ),
            'source_wallet' => $this->when(
                $this->relationLoaded('sourceWallet'),
                fn (): array => ['uuid' => $this->sourceWallet->public_id, 'name' => $this->sourceWallet->name],
            ),
            'destination_wallet' => $this->when(
                $this->relationLoaded('destinationWallet'),
                fn (): array => ['uuid' => $this->destinationWallet->public_id, 'name' => $this->destinationWallet->name],
            ),
            'allocations' => ContributionPaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'external_reference' => $this->when($this->relationLoaded('financialTransaction'), $this->external_reference),
            'payer_name' => $this->when($this->relationLoaded('financialTransaction'), $this->payer_name),
            'payer_phone' => $this->when($this->relationLoaded('financialTransaction'), $this->payer_phone),
            'notes' => $this->when($this->relationLoaded('financialTransaction'), $this->notes),
            'reversal' => $this->reversalPayment === null ? null : [
                'uuid' => $this->reversalPayment->public_id,
                'payment_number' => $this->reversalPayment->payment_number,
            ],
        ];
    }
}
