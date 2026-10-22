<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Complaints\Models\ComplaintMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComplaintMessage */
class ComplaintMessageResource extends JsonResource
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
            'author_uuid' => $this->author->public_id,
            'visibility' => $this->visibility,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
