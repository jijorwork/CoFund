<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Withdrawals\Models\WithdrawalProduct;

class CalculateContributionWithdrawalRestrictions
{
    /** @return array<string, mixed> */
    public function calculate(Member $member, WithdrawalProduct $product, int $eligibleBalanceMinor): array
    {
        $active = MemberPlanEnrolment::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('status', EnrolmentStatus::Active)->exists();
        $blocked = $product->require_active_contribution_enrolment && ! $active;

        return ['blocked' => $blocked, 'restricted_amount_minor' => 0, 'eligible_contribution_balance_minor' => $eligibleBalanceMinor, 'locked_balance_minor' => 0, 'immature_balance_minor' => 0, 'minimum_required_balance_minor' => 0, 'blocking_reasons' => $blocked ? ['active_contribution_enrolment_required'] : [], 'warnings' => []];
    }
}
