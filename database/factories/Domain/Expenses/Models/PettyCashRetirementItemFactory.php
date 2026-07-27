<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\PettyCashRetirement;
use App\Domain\Expenses\Models\PettyCashRetirementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashRetirementItem>
 */
class PettyCashRetirementItemFactory extends Factory
{
    protected $model = PettyCashRetirementItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'petty_cash_retirement_id' => PettyCashRetirement::factory(),
            'organisation_id' => fn (array $attributes) => PettyCashRetirement::query()->whereKey($attributes['petty_cash_retirement_id'])->firstOrFail()->organisation_id,
            'expense_category_id' => ExpenseCategory::factory(),
            'ledger_account_id' => LedgerAccount::factory()->category(AccountCategory::Expense),
            'description' => fake()->sentence(3), 'amount_minor' => 10000,
            'expense_date' => today(), 'sequence_number' => 1,
        ];
    }
}
