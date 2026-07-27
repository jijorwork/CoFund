<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Membership\Models\Member;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Withdrawals\Models\SavingsHold;

class CalculateWelfareWithdrawalRestrictions
{
    /** @return array<string, mixed> */
    public function calculate(Member $member, string $policy): array
    {
        $holds = (int) SavingsHold::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('hold_type', 'welfare_commitment')->where('status', 'active')->sum('amount_minor');
        $claims = WelfareClaim::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->whereIn('status', ['submitted', 'under_review', 'pending_documents', 'pending_verification', 'pending_approval'])->get(['public_id', 'claim_number', 'status']);
        $blocked = in_array($policy, ['block_during_pending_claim', 'require_welfare_officer_clearance'], true) && $claims->isNotEmpty();

        return ['blocked' => $blocked, 'restricted_amount_minor' => 0, 'active_welfare_holds_minor' => $holds, 'pending_claim_count' => $claims->count(), 'blocking_reasons' => $blocked ? ['pending_welfare_claim_policy_blocks_withdrawal'] : [], 'warnings' => [], 'affected_records' => $claims->map(fn (WelfareClaim $claim): array => ['uuid' => $claim->public_id, 'claim_number' => $claim->claim_number])->all()];
    }
}
