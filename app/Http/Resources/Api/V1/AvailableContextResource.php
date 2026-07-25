<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganisationUserRole
 */
class AvailableContextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'organisation_uuid' => $this->organisation->public_id,
            'organisation_name' => $this->organisation->name,
            'branch_uuid' => $this->branch?->public_id,
            'branch_name' => $this->branch?->name,
            'role_assignment_uuid' => $this->public_id,
            'role_name' => $this->role->name,
            'role_label' => SystemRole::from($this->role->name)->label(),
        ];
    }
}
