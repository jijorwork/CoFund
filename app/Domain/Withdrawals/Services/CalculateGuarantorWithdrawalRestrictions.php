<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Membership\Models\Member;
use App\Domain\Withdrawals\Models\SavingsHold;

class CalculateGuarantorWithdrawalRestrictions
{
    /** @return array<string, mixed> */
    public function calculate(Member $member, string $policy): array
    {
        $guarantees = LoanGuarantor::query()->where('organisation_id', $member->organisation_id)->where('guarantor_member_id', $member->getKey())->where('status', GuarantorStatus::Accepted)->get();
        $exposure = (int) $guarantees->sum('guaranteed_amount_minor');
        $held = (int) SavingsHold::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('hold_type', 'guarantor_security')->where('status', 'active')->sum('amount_minor');
        $blocked = in_array($policy, ['block_while_active_guarantee_exists', 'require_guarantor_release'], true) && $guarantees->isNotEmpty();
        $required = $policy === 'retain_guaranteed_amount' ? $exposure : 0;

        return ['blocked' => $blocked, 'accepted_guarantee_count' => $guarantees->count(), 'guarantee_exposure_minor' => $exposure, 'required_security_minor' => $required, 'existing_guarantor_holds_minor' => $held, 'restricted_amount_minor' => max(0, $required - $held), 'blocking_reasons' => $blocked ? ['active_guarantee_policy_blocks_withdrawal'] : [], 'warnings' => [], 'affected_guarantees' => $guarantees->map(fn (LoanGuarantor $guarantee): array => ['uuid' => $guarantee->public_id, 'guaranteed_amount_minor' => $guarantee->guaranteed_amount_minor])->all()];
    }
}
