<?php

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;

class BeneficiaryAllocationService
{
    public function currentAllocatedPercentage(Member $member, ?MemberContact $excluding = null): string
    {
        $query = MemberContact::query()
            ->whereBelongsTo($member)
            ->where('contact_type', MemberContactType::Beneficiary)
            ->where('status', MemberContactStatus::Active);

        if ($excluding !== null) {
            $query->whereKeyNot($excluding);
        }

        return number_format((float) $query->sum('percentage_entitlement'), 2, '.', '');
    }

    public function remainingPercentage(Member $member, ?MemberContact $excluding = null): string
    {
        return $this->fromBasisPoints(
            10000 - $this->toBasisPoints($this->currentAllocatedPercentage($member, $excluding)),
        );
    }

    public function validateProposedAllocation(
        Member $member,
        string $percentage,
        ?MemberContact $excluding = null,
    ): bool {
        $allocated = $this->toBasisPoints($this->currentAllocatedPercentage($member, $excluding));
        $proposed = $this->toBasisPoints($percentage);

        return $proposed >= 0 && $allocated + $proposed <= 10000;
    }

    public function assertTotalDoesNotExceedOneHundred(
        Member $member,
        string $percentage,
        ?MemberContact $excluding = null,
    ): void {
        if (! $this->validateProposedAllocation($member, $percentage, $excluding)) {
            throw new InvalidMembershipOperation('Beneficiary allocation cannot exceed 100 percent.');
        }
    }

    private function toBasisPoints(string $percentage): int
    {
        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromBasisPoints(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 2, '.', '');
    }
}
