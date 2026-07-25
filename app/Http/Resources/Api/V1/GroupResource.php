<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Membership\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Group */
class GroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->group_type->value,
            'description' => $this->description,
            'status' => $this->status->value,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'active_members_count' => $this->when(
                isset($this->active_members_count),
                $this->active_members_count,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
