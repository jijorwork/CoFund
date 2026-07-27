<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationDelivery */
class NotificationDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'dispatch' => $this->whenLoaded('dispatch', fn (): array => [
                'uuid' => $this->dispatch->public_id,
                'event_key' => $this->dispatch->event_key,
            ]),
            'channel' => $this->channel->value,
            'destination_masked' => $this->destination_masked,
            'status' => $this->status,
            'attempt_count' => $this->attempt_count,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failure' => $this->failure_code === null ? null : [
                'code' => $this->failure_code,
                'reason' => $this->failure_reason,
            ],
            'next_retry_at' => $this->next_attempt_at?->toIso8601String(),
        ];
    }
}
