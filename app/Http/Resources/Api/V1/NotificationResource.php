<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\InAppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InAppNotification */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'event_key' => $this->event_key,
            'category' => $this->category->value,
            'title' => $this->title,
            'body' => $this->body,
            'action_label' => $this->action_label,
            'action_url' => $this->action_url,
            'priority' => $this->priority->value,
            'mandatory' => $this->mandatory,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'is_archived' => $this->archived_at !== null,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
