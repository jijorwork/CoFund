<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Membership\Models\MemberDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MemberDocument */
class MemberDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'document_type' => $this->document_type->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'verification_status' => $this->verification_status->value,
            'verified_at' => $this->verified_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
