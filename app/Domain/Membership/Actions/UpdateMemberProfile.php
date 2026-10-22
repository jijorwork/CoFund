<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateMemberProfile
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
        private PhoneNumberNormalizer $phones,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function handle(Member $member, array $profile, ?Branch $branch): Member
    {
        if ($member->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new InvalidMembershipOperation;
        }

        $this->branchScope->assertAccessibleRecord($member->branch_id);
        $this->branchScope->assertAccessibleBranch($branch);
        $profile = Arr::only($profile, [
            'first_name',
            'middle_name',
            'last_name',
            'preferred_name',
            'gender',
            'date_of_birth',
            'phone',
            'alternate_phone',
            'email',
            'national_id_type',
            'national_id_number',
            'occupation',
            'residential_address',
            'postal_address',
            'notes',
        ]);

        if (array_key_exists('phone', $profile)) {
            $profile['phone'] = $this->phones->normalize($profile['phone']);
        }

        if (array_key_exists('alternate_phone', $profile)) {
            $profile['alternate_phone'] = $this->phones->normalize($profile['alternate_phone']);
        }

        return DB::transaction(function () use ($member, $profile, $branch): Member {
            $lockedMember = Member::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMember->organisation_id !== $this->tenantContext->organisation()->getKey()) {
                throw new InvalidMembershipOperation;
            }

            $this->branchScope->assertAccessibleRecord($lockedMember->branch_id);
            $lockedMember->update([
                ...$profile,
                'branch_id' => $branch?->getKey(),
            ]);

            return $lockedMember->refresh()->load(['branch', 'user']);
        });
    }
}
