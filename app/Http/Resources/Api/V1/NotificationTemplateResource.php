<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationTemplate */
class NotificationTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'template_key' => $this->template_key,
            'event_key' => $this->event_key,
            'channel' => $this->channel->value,
            'locale' => $this->locale,
            'name' => $this->name,
            'version' => $this->version,
            'status' => $this->status,
            'is_system_default' => $this->is_system_default,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'retired_at' => $this->retired_at?->toIso8601String(),
        ];
    }
}
