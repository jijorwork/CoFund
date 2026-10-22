<?php

namespace Database\Factories\Domain\Collections\Models;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $payment = ContributionPayment::factory()->create();
        $code = Str::random(48);

        return [
            'organisation_id' => $payment->organisation_id,
            'branch_id' => $payment->branch_id,
            'contribution_payment_id' => $payment->getKey(),
            'financial_transaction_id' => $payment->financial_transaction_id,
            'receipt_number' => fake()->unique()->bothify('ORG-RCP-2026-######'),
            'receipt_type' => ReceiptType::ContributionPayment,
            'status' => ReceiptStatus::Issued,
            'issued_at' => now(),
            'issued_by_user_id' => User::factory(),
            'member_id' => $payment->member_id,
            'payment_method' => $payment->payment_method->value,
            'amount_minor' => $payment->amount_minor,
            'currency_code' => $payment->currency_code,
            'verification_code' => $code,
            'verification_hash' => hash('sha256', $code),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => ['status' => ReceiptStatus::Issued]);
    }

    public function reversed(): static
    {
        return $this->state(fn (): array => ['status' => ReceiptStatus::Reversed]);
    }

    public function contributionPayment(): static
    {
        return $this->state(fn (): array => ['receipt_type' => ReceiptType::ContributionPayment]);
    }

    public function reversal(): static
    {
        return $this->state(fn (): array => ['receipt_type' => ReceiptType::ContributionReversal]);
    }
}
