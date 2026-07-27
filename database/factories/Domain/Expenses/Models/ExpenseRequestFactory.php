<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRequest>
 */
class ExpenseRequestFactory extends Factory
{
    protected $model = ExpenseRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => fn () => ($category = ExpenseCategory::factory()->create())->organisation_id,
            'expense_number' => fake()->unique()->bothify('ORG-EXP-2026-######'),
            'requester_user_id' => User::factory(), 'beneficiary_type' => 'requester',
            'expense_category_id' => fn (array $attributes) => ExpenseCategory::factory()->create(['organisation_id' => $attributes['organisation_id']])->getKey(),
            'title' => fake()->sentence(3), 'purpose' => fake()->sentence(),
            'requested_amount_minor' => 10000, 'paid_amount_minor' => 0, 'outstanding_amount_minor' => 0,
            'currency_code' => 'GHS', 'status' => 'draft', 'approval_status' => 'not_submitted',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => 'submitted', 'approval_status' => 'pending', 'submitted_at' => now()]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'approval_status' => 'approved', 'approved_amount_minor' => 10000, 'outstanding_amount_minor' => 10000, 'approved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => 'rejected', 'approval_status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => 'Rejected']);
    }

    public function partiallyPaid(): static
    {
        return $this->approved()->state(fn (): array => ['status' => 'partially_paid', 'paid_amount_minor' => 5000, 'outstanding_amount_minor' => 5000]);
    }

    public function paid(): static
    {
        return $this->approved()->state(fn (): array => ['status' => 'paid', 'paid_amount_minor' => 10000, 'outstanding_amount_minor' => 0]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Cancelled']);
    }

    public function completed(): static
    {
        return $this->paid()->state(fn (): array => ['status' => 'completed', 'completed_at' => now()]);
    }
}
