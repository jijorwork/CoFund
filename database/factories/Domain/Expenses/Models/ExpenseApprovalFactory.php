<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Expenses\Models\ExpenseApproval;
use App\Domain\Expenses\Models\ExpenseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseApproval>
 */
class ExpenseApprovalFactory extends Factory
{
    protected $model = ExpenseApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_request_id' => ExpenseRequest::factory(),
            'organisation_id' => fn (array $attributes) => ExpenseRequest::query()->whereKey($attributes['expense_request_id'])->firstOrFail()->organisation_id,
            'approval_level' => 'primary', 'decision' => 'pending',
            'requested_at' => now(), 'sequence_number' => 1,
        ];
    }
}
