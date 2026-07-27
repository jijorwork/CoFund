<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Notifications\Models\NotificationDeliveryAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationDeliveryAttempt */
class NotificationDeliveryAttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status,
            'failure_code' => $this->failure_code,
            'failure_reason' => $this->failure_reason,
            'duration_ms' => $this->duration_ms,
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
