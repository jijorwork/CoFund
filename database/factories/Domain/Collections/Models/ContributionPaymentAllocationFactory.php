<?php

namespace Database\Factories\Domain\Collections\Models;

use App\Domain\Collections\Enums\PaymentAllocationStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributionPaymentAllocation> */
class ContributionPaymentAllocationFactory extends Factory
{
    protected $model = ContributionPaymentAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $payment = ContributionPayment::factory()->create();
        $obligation = ContributionObligation::factory()->create([
            'organisation_id' => $payment->organisation_id,
            'member_plan_enrolment_id' => $payment->member_plan_enrolment_id,
            'contribution_plan_id' => $payment->contribution_plan_id,
            'member_id' => $payment->member_id,
        ]);

        return [
            'organisation_id' => $payment->organisation_id,
            'contribution_payment_id' => $payment->getKey(),
            'contribution_obligation_id' => $obligation->getKey(),
            'member_plan_enrolment_id' => $obligation->member_plan_enrolment_id,
            'member_id' => $payment->member_id,
            'amount_minor' => min(10000, $obligation->outstanding_amount_minor),
            'currency_code' => $payment->currency_code,
            'allocation_order' => 1,
            'status' => PaymentAllocationStatus::Active,
            'allocated_at' => now(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => PaymentAllocationStatus::Active]);
    }

    public function reversed(): static
    {
        return $this->state(fn (): array => ['status' => PaymentAllocationStatus::Reversed]);
    }

    public function partial(): static
    {
        return $this->state(fn (): array => ['amount_minor' => 5000]);
    }

    public function full(): static
    {
        return $this->state(fn (): array => ['amount_minor' => 10000]);
    }
}
