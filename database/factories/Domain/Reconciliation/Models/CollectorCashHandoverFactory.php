<?php

namespace Database\Factories\Domain\Reconciliation\Models;

use App\Domain\Reconciliation\Enums\CashHandoverStatus;
use App\Domain\Reconciliation\Models\CollectorCashHandover;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CollectorCashHandover> */
class CollectorCashHandoverFactory extends Factory
{
    protected $model = CollectorCashHandover::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $session = CollectorCollectionSession::factory()->create(['status' => 'submitted', 'active_wallet_key' => null]);
        $destination = Wallet::factory()->officeCash()->create(['organisation_id' => $session->organisation_id, 'branch_id' => $session->branch_id]);

        return [
            'organisation_id' => $session->organisation_id, 'branch_id' => $session->branch_id,
            'collector_collection_session_id' => $session->getKey(), 'handover_number' => fake()->unique()->bothify('ORG-HOV-2026-######'),
            'collector_user_id' => $session->collector_user_id, 'collector_wallet_id' => $session->collector_wallet_id,
            'destination_wallet_id' => $destination->getKey(), 'declared_cash_minor' => 10000,
            'currency_code' => $session->currency_code, 'status' => CashHandoverStatus::Draft,
            'idempotency_key' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => CashHandoverStatus::Submitted, 'handed_over_at' => now()]);
    }

    public function received(): static
    {
        return $this->submitted()->state(fn (): array => ['status' => CashHandoverStatus::Received, 'accepted_cash_minor' => 10000, 'received_at' => now()]);
    }

    public function approved(): static
    {
        return $this->received()->state(fn (): array => ['status' => CashHandoverStatus::Approved, 'approved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(fn (): array => ['status' => CashHandoverStatus::Rejected, 'rejection_reason' => 'Evidence mismatch']);
    }

    public function exact(): static
    {
        return $this->state(fn (): array => ['declared_cash_minor' => 10000, 'accepted_cash_minor' => 10000, 'shortage_minor' => 0, 'overage_minor' => 0]);
    }

    public function shortage(): static
    {
        return $this->state(fn (): array => ['declared_cash_minor' => 10000, 'accepted_cash_minor' => 9000, 'shortage_minor' => 1000, 'overage_minor' => 0]);
    }

    public function overage(): static
    {
        return $this->state(fn (): array => ['declared_cash_minor' => 10000, 'accepted_cash_minor' => 11000, 'shortage_minor' => 0, 'overage_minor' => 1000]);
    }
}
