<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Auditing\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditEvent */
class AuditEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'actor_uuid' => $this->actor?->public_id,
            'event_key' => $this->event_key,
            'subject_type' => $this->subject_type,
            'subject_public_id' => $this->subject_public_id,
            'details' => $this->details,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
