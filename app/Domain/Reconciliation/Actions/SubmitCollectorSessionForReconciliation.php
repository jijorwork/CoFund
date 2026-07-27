<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashDeclaration;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\CalculateCollectorCashVariance;
use App\Domain\Reconciliation\Services\CalculateCollectorSessionExpectedAmounts;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitCollectorSessionForReconciliation
{
    public function __construct(
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private CalculateCollectorSessionExpectedAmounts $expected,
        private CalculateCollectorCashVariance $variance,
    ) {}

    public function handle(User $actor, CollectorCollectionSession $session): CollectorCollectionSession
    {
        $this->authorization->authorize(FoundationPermission::CollectionSessionsSubmit->value);
        $this->scope->assertCollectorActor($actor, $session->collector_user_id);
        $this->scope->assertBranch($session->branch_id);

        return DB::transaction(function () use ($actor, $session): CollectorCollectionSession {
            $locked = CollectorCollectionSession::query()->lockForUpdate()->whereKey($session->getKey())->firstOrFail();

            if (! in_array($locked->status, [CollectorSessionStatus::Closed, CollectorSessionStatus::Rejected], true)) {
                throw new InvalidReconciliationOperation('Only a closed or rejected session can be submitted.');
            }

            $declaration = CollectorCashDeclaration::query()
                ->where('collector_collection_session_id', $locked->getKey())
                ->where('status', CashDeclarationStatus::Active)
                ->first();

            if ($declaration === null) {
                throw new InvalidReconciliationOperation('An active cash declaration is required.');
            }

            $expected = $this->expected->calculate($locked);
            $variance = $this->variance->calculate($expected['net_expected_cash_minor'], $declaration->declared_cash_minor);
            $locked->update([
                'status' => CollectorSessionStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actor->getKey(),
                'expected_cash_minor' => $expected['net_expected_cash_minor'],
                'expected_non_cash_minor' => 0,
                'declared_cash_minor' => $declaration->declared_cash_minor,
                'shortage_minor' => $variance->shortageMinor,
                'overage_minor' => $variance->overageMinor,
                'rejected_at' => null,
                'rejected_by_user_id' => null,
                'rejection_reason' => null,
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
