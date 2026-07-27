<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'code' => fake()->unique()->bothify('EXP-###'),
            'name' => fake()->words(2, true),
            'default_ledger_account_id' => function (array $attributes): int {
                return LedgerAccount::factory()->for(Organisation::query()->whereKey($attributes['organisation_id'])->firstOrFail())
                    ->category(AccountCategory::Expense)->create(['purpose' => AccountPurpose::OperatingExpense])->getKey();
            },
            'requires_receipt' => false, 'requires_preapproval' => true,
            'requires_secondary_approval' => false, 'status' => 'active',
            'created_by_user_id' => User::factory(),
        ];
    }
}
