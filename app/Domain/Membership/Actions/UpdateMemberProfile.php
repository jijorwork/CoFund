<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use App\Domain\Organisations\Models\Branch;

class UpdateMemberProfile
{
    public function __construct(
        private MembershipBranchScope $branchScope,
        private PhoneNumberNormalizer $phones,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function handle(Member $member, array $profile, ?Branch $branch): Member
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);
        $this->branchScope->assertAccessibleBranch($branch);

        if (array_key_exists('phone', $profile)) {
            $profile['phone'] = $this->phones->normalize($profile['phone']);
        }

        if (array_key_exists('alternate_phone', $profile)) {
            $profile['alternate_phone'] = $this->phones->normalize($profile['alternate_phone']);
        }

        $member->update([
            ...$profile,
            'branch_id' => $branch?->getKey(),
        ]);

        return $member->refresh()->load(['branch', 'user']);
    }
}
