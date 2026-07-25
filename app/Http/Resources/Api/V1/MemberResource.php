<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Member */
class MemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mayViewSensitive = app(RoleAuthorization::class)
            ->can(FoundationPermission::MembersUpdate->value);

        return [
            'uuid' => $this->public_id,
            'membership_number' => $this->membership_number,
            'full_name' => $this->fullName(),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'email' => $this->email,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'status' => $this->status->value,
            'joined_at' => $this->joined_at->toDateString(),
            'linked_user' => $this->user_id !== null,
            'gender' => $this->when($mayViewSensitive, $this->gender?->value),
            'date_of_birth' => $this->when($mayViewSensitive, $this->date_of_birth?->toDateString()),
            'national_id_type' => $this->when($mayViewSensitive, $this->national_id_type),
            'national_id_number' => $this->when(
                $mayViewSensitive,
                $this->national_id_number === null
                    ? null
                    : Str::mask($this->national_id_number, '*', 0, max(0, Str::length($this->national_id_number) - 4)),
            ),
            'residential_address' => $this->when($mayViewSensitive, $this->residential_address),
            'groups' => $this->whenLoaded('groupMemberships', fn () => $this->groupMemberships
                ->where('status.value', 'active')
                ->map(fn ($membership): array => [
                    'uuid' => $membership->group->public_id,
                    'name' => $membership->group->name,
                ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
