<?php

namespace Database\Factories\Domain\Reconciliation\Models;

use App\Domain\Reconciliation\Enums\CashVarianceStatus;
use App\Domain\Reconciliation\Enums\CashVarianceType;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CollectorCashVariance> */
class CollectorCashVarianceFactory extends Factory
{
    protected $model = CollectorCashVariance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $session = CollectorCollectionSession::factory()->create(['status' => 'approved', 'active_wallet_key' => null]);

        return [
            'organisation_id' => $session->organisation_id, 'branch_id' => $session->branch_id,
            'collector_collection_session_id' => $session->getKey(), 'collector_user_id' => $session->collector_user_id,
            'collector_wallet_id' => $session->collector_wallet_id, 'variance_type' => CashVarianceType::Shortage,
            'amount_minor' => 1000, 'currency_code' => $session->currency_code, 'status' => CashVarianceStatus::Open,
            'reported_at' => now(), 'reported_by_user_id' => $session->opened_by_user_id,
        ];
    }
}
