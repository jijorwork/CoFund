<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Complaints\Models\ComplaintCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComplaintCase */
class ComplaintCaseResource extends JsonResource
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
            'member_uuid' => $this->member?->public_id,
            'reported_by_uuid' => $this->reportedBy->public_id,
            'assigned_to_uuid' => $this->assignedTo?->public_id,
            'subject' => $this->subject,
            'category' => $this->category,
            'status' => $this->status->value,
            'resolution_summary' => $this->resolution_summary,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'messages' => ComplaintMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
