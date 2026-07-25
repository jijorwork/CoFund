<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Services\BeneficiaryAllocationService;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use Illuminate\Support\Facades\DB;

class UpdateMemberContact
{
    public function __construct(
        private MembershipBranchScope $branchScope,
        private BeneficiaryAllocationService $allocation,
        private PhoneNumberNormalizer $phones,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Member $member, MemberContact $contact, array $attributes): MemberContact
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if ($contact->member_id !== $member->getKey()) {
            throw new InvalidMembershipOperation;
        }

        return DB::transaction(function () use ($member, $contact, $attributes): MemberContact {
            Member::query()->lockForUpdate()->whereKey($member->getKey())->firstOrFail();
            $locked = MemberContact::query()->lockForUpdate()->whereKey($contact->getKey())->firstOrFail();
            $type = isset($attributes['contact_type'])
                ? MemberContactType::from($attributes['contact_type'])
                : $locked->contact_type;
            $percentage = $attributes['percentage_entitlement']
                ?? $locked->percentage_entitlement;

            if ($type === MemberContactType::Beneficiary) {
                $this->allocation->assertTotalDoesNotExceedOneHundred(
                    $member,
                    (string) $percentage,
                    $locked,
                );
            } elseif ($percentage !== null) {
                throw new InvalidMembershipOperation;
            }

            if (array_key_exists('phone', $attributes)) {
                $attributes['phone'] = $this->phones->normalize($attributes['phone']);
            }

            if (array_key_exists('alternate_phone', $attributes)) {
                $attributes['alternate_phone'] = $this->phones->normalize($attributes['alternate_phone']);
            }

            $primary = (bool) ($attributes['is_primary'] ?? $locked->is_primary);
            $locked->update([
                ...$attributes,
                'contact_type' => $type,
                'percentage_entitlement' => $type === MemberContactType::Beneficiary
                    ? $percentage
                    : null,
                'primary_scope_key' => $primary
                    ? MemberContact::primaryScopeKey($member, $type)
                    : null,
            ]);

            return $locked->refresh();
        });
    }
}
