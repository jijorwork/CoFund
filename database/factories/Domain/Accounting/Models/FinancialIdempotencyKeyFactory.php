<?php

namespace Database\Factories\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\IdempotencyStatus;
use App\Domain\Accounting\Models\FinancialIdempotencyKey;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialIdempotencyKey> */
class FinancialIdempotencyKeyFactory extends Factory
{
    protected $model = FinancialIdempotencyKey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'status' => IdempotencyStatus::Processing,
            'locked_at' => now(),
        ];
    }
}
