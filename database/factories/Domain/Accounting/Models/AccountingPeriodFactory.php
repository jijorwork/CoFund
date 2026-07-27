<?php

namespace Database\Factories\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountingPeriod> */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => 'Financial Year '.now()->year,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => AccountingPeriodStatus::Open]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => AccountingPeriodStatus::Closed, 'closed_at' => now()]);
    }

    public function reopened(): static
    {
        return $this->state(fn (): array => ['status' => AccountingPeriodStatus::Reopened, 'reopened_at' => now()]);
    }
}
