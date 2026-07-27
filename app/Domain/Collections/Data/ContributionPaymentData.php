<?php

namespace App\Domain\Collections\Data;

use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Models\Wallet;
use Carbon\CarbonImmutable;

final readonly class ContributionPaymentData
{
    /** @param list<PaymentAllocationData> $selectedAllocations */
    public function __construct(
        public Member $member,
        public ?MemberPlanEnrolment $enrolment,
        public ?ContributionPlan $plan,
        public int $amountMinor,
        public string $currencyCode,
        public ContributionPaymentMethod $paymentMethod,
        public CarbonImmutable $paymentDate,
        public Wallet $sourceWallet,
        public Wallet $destinationWallet,
        public PaymentAllocationStrategy $allocationStrategy,
        public string $idempotencyKey,
        public array $selectedAllocations = [],
        public ?string $externalReference = null,
        public ?string $payerName = null,
        public ?string $payerPhone = null,
        public ?string $notes = null,
    ) {}
}
