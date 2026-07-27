<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\ExpenseDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseDocument */
class ExpenseDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'document_type' => $this->document_type, 'original_name' => $this->original_name, 'mime_type' => $this->mime_type, 'size_bytes' => $this->size_bytes, 'checksum' => $this->checksum, 'created_at' => $this->created_at?->toISOString()];
    }
}
