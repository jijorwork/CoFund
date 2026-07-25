<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Services\BeneficiaryAllocationService;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use Illuminate\Support\Facades\DB;

class AddMemberContact
{
    public function __construct(
        private MembershipBranchScope $branchScope,
        private BeneficiaryAllocationService $allocation,
        private PhoneNumberNormalizer $phones,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Member $member, array $attributes): MemberContact
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        return DB::transaction(function () use ($member, $attributes): MemberContact {
            Member::query()->lockForUpdate()->whereKey($member->getKey())->firstOrFail();
            $type = MemberContactType::from($attributes['contact_type']);
            $percentage = $attributes['percentage_entitlement'] ?? null;

            if ($type === MemberContactType::Beneficiary) {
                if ($percentage === null) {
                    throw new InvalidMembershipOperation;
                }
                $this->allocation->assertTotalDoesNotExceedOneHundred($member, (string) $percentage);
            } elseif ($percentage !== null) {
                throw new InvalidMembershipOperation;
            }

            $primary = (bool) ($attributes['is_primary'] ?? false);

            return MemberContact::query()->create([
                ...$attributes,
                'organisation_id' => $member->organisation_id,
                'member_id' => $member->getKey(),
                'contact_type' => $type,
                'phone' => $this->phones->normalize((string) $attributes['phone']),
                'alternate_phone' => $this->phones->normalize($attributes['alternate_phone'] ?? null),
                'status' => MemberContactStatus::Active,
                'primary_scope_key' => $primary
                    ? MemberContact::primaryScopeKey($member, $type)
                    : null,
            ])->load('member');
        });
    }
}
