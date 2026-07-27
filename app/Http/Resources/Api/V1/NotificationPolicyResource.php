<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationPolicy */
class NotificationPolicyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'event_key' => $this->event_key,
            'category' => $this->category->value,
            'enabled' => $this->enabled,
            'mandatory_override' => $this->mandatory_override,
            'priority' => $this->priority->value,
            'channels' => [
                'in_app' => $this->in_app_enabled,
                'email' => $this->email_enabled,
                'sms' => $this->sms_enabled,
                'messaging' => $this->messaging_enabled,
            ],
            'digest_mode' => $this->digest_mode,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
