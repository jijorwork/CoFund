<?php

namespace Database\Factories\Domain\Reconciliation\Models;

use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Domain\Reconciliation\Models\CollectorCashDeclaration;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CollectorCashDeclaration> */
class CollectorCashDeclarationFactory extends Factory
{
    protected $model = CollectorCashDeclaration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $session = CollectorCollectionSession::factory()->create(['status' => 'closed', 'active_wallet_key' => null, 'closed_at' => now()]);

        return [
            'organisation_id' => $session->organisation_id, 'collector_collection_session_id' => $session->getKey(),
            'declaration_number' => fake()->unique()->bothify('ORG-DEC-2026-######'), 'declared_cash_minor' => 10000,
            'currency_code' => $session->currency_code, 'denomination_breakdown' => [['denomination_minor' => 10000, 'quantity' => 1]],
            'declared_at' => now(), 'declared_by_user_id' => $session->collector_user_id,
            'status' => CashDeclarationStatus::Active, 'active_session_key' => (string) $session->getKey(),
        ];
    }
}
