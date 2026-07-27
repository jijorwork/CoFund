<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationPreference */
class NotificationPreferenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'category' => $this->category->value,
            'event_key' => $this->event_key,
            'channels' => [
                'in_app' => $this->in_app_enabled,
                'email' => $this->email_enabled,
                'sms' => $this->sms_enabled,
                'messaging' => $this->messaging_enabled,
            ],
            'digest_frequency' => $this->digest_frequency,
            'quiet_hours' => [
                'start' => $this->quiet_hours_start,
                'end' => $this->quiet_hours_end,
                'timezone' => $this->timezone,
            ],
            'locale' => $this->locale,
        ];
    }
}
