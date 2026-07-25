<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\MemberContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin MemberContact */
class MemberContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mayManage = app(RoleAuthorization::class)
            ->can(FoundationPermission::MembersManageContacts->value);

        return [
            'uuid' => $this->public_id,
            'type' => $this->contact_type->value,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'email' => $this->email,
            'percentage_entitlement' => $this->percentage_entitlement,
            'is_primary' => $this->is_primary,
            'is_emergency_contact' => $this->is_emergency_contact,
            'status' => $this->status->value,
            'verified' => $this->verified_at !== null,
            'national_id_type' => $this->when($mayManage, $this->national_id_type),
            'national_id_number' => $this->when(
                $mayManage,
                $this->national_id_number === null
                    ? null
                    : Str::mask($this->national_id_number, '*', 0, max(0, Str::length($this->national_id_number) - 4)),
            ),
        ];
    }
}
