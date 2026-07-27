<?php

namespace Database\Factories\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerAccount> */
class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'parent_id' => null,
            'code' => fake()->unique()->numerify('9###'),
            'name' => fake()->words(3, true),
            'category' => AccountCategory::Asset,
            'normal_balance' => AccountCategory::Asset->normalBalance(),
            'purpose' => AccountPurpose::Other,
            'currency_code' => 'GHS',
            'allows_direct_posting' => true,
            'is_system_account' => false,
            'status' => LedgerAccountStatus::Active,
            'activated_at' => now(),
        ];
    }

    public function category(AccountCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category,
            'normal_balance' => $category->normalBalance(),
        ]);
    }

    public function asset(): static
    {
        return $this->category(AccountCategory::Asset);
    }

    public function liability(): static
    {
        return $this->category(AccountCategory::Liability);
    }

    public function income(): static
    {
        return $this->category(AccountCategory::Income);
    }

    public function expense(): static
    {
        return $this->category(AccountCategory::Expense);
    }

    public function equity(): static
    {
        return $this->category(AccountCategory::Equity);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => LedgerAccountStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => LedgerAccountStatus::Suspended]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => LedgerAccountStatus::Closed, 'closed_at' => now()]);
    }

    public function directPosting(bool $allowed = true): static
    {
        return $this->state(fn (): array => ['allows_direct_posting' => $allowed]);
    }

    public function parentAccount(): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => self::new()->state([
                'organisation_id' => $attributes['organisation_id'],
                'currency_code' => $attributes['currency_code'],
            ]),
        ]);
    }

    public function branchSpecific(): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => Branch::factory()->state(['organisation_id' => $attributes['organisation_id']]),
        ]);
    }
}
