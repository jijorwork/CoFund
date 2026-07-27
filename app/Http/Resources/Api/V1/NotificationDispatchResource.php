<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationDispatch */
class NotificationDispatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'event_key' => $this->event_key,
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'source_uuid' => $this->source_uuid,
            'recipient_type' => $this->recipient_type,
            'status' => $this->status,
            'mandatory' => $this->mandatory,
            'digestible' => $this->digestible,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
