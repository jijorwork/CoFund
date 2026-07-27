<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationDigest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationDigest */
class NotificationDigestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'frequency' => $this->frequency,
            'channel' => $this->channel->value,
            'period_start' => $this->period_start->toIso8601String(),
            'period_end' => $this->period_end->toIso8601String(),
            'status' => $this->status,
            'item_count' => $this->item_count,
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
