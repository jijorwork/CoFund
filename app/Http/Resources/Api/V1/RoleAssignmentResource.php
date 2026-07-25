<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganisationUserRole
 */
class RoleAssignmentResource extends JsonResource
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
            'organisation_uuid' => $this->organisation->public_id,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'user' => [
                'uuid' => $this->user->public_id,
                'name' => $this->user->name,
            ],
            'role' => [
                'name' => $this->role->name,
                'label' => SystemRole::from($this->role->name)->label(),
            ],
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'assigned_at' => $this->assigned_at->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'revocation_reason' => $this->revocation_reason,
        ];
    }
}
