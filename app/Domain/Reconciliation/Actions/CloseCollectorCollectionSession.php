<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\CalculateCollectorSessionExpectedAmounts;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloseCollectorCollectionSession
{
    public function __construct(
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private CalculateCollectorSessionExpectedAmounts $expected,
    ) {}

    public function handle(User $actor, CollectorCollectionSession $session): CollectorCollectionSession
    {
        $this->authorization->authorize(FoundationPermission::CollectionSessionsClose->value);
        $this->scope->assertCollectorActor($actor, $session->collector_user_id);
        $this->scope->assertBranch($session->branch_id);

        return DB::transaction(function () use ($actor, $session): CollectorCollectionSession {
            $locked = CollectorCollectionSession::query()->lockForUpdate()->whereKey($session->getKey())->firstOrFail();

            if ($locked->status !== CollectorSessionStatus::Open) {
                throw new InvalidReconciliationOperation('Only an open session can be closed.');
            }

            $amounts = $this->expected->calculate($locked);
            $locked->update([
                'status' => CollectorSessionStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $actor->getKey(),
                'expected_cash_minor' => $amounts['net_expected_cash_minor'],
                'expected_non_cash_minor' => 0,
                'active_wallet_key' => null,
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
