<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\ExpenseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRequestItem>
 */
class ExpenseRequestItemFactory extends Factory
{
    protected $model = ExpenseRequestItem::class;

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
            'description' => fake()->sentence(3), 'quantity' => 1,
            'unit_amount_minor' => 10000, 'line_total_minor' => 10000,
            'currency_code' => 'GHS', 'sequence_number' => 1,
        ];
    }
}
