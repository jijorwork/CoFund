<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Membership\Models\Member;

class CalculateMemberUnallocatedContributionBalance
{
    public function calculate(Member $member): int
    {
        return (int) ContributionPayment::query()
            ->whereBelongsTo($member)
            ->whereNot('status', ContributionPaymentStatus::Reversed)
            ->whereNull('reversed_payment_id')
            ->sum('unallocated_amount_minor');
    }
}
