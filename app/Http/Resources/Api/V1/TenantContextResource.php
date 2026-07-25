<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantContext
 */
class TenantContextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organisation = $this->resource->organisation();
        $branch = $this->resource->branch();

        return [
            'organisation_uuid' => $organisation->public_id,
            'organisation_name' => $organisation->name,
            'branch_uuid' => $branch?->public_id,
            'branch_name' => $branch?->name,
        ];
    }
}
