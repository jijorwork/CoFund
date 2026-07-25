<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => SystemRole::from($this->name)->label(),
        ];
    }
}
