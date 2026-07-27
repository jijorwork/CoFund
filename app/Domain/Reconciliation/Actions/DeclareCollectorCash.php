<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Enums\ReconciliationSequenceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashDeclaration;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\ReconciliationNumberGenerator;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Domain\Reconciliation\Services\ValidateDenominationBreakdown;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeclareCollectorCash
{
    public function __construct(
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private ValidateDenominationBreakdown $denominations,
        private ReconciliationNumberGenerator $numbers,
    ) {}

    /** @param list<array{denomination_minor:int, quantity:int}> $breakdown */
    public function handle(User $actor, CollectorCollectionSession $session, int $amountMinor, array $breakdown, ?string $notes = null): CollectorCashDeclaration
    {
        $this->authorization->authorize(FoundationPermission::CashDeclarationsCreate->value);
        $this->scope->assertCollectorActor($actor, $session->collector_user_id);
        $this->scope->assertBranch($session->branch_id);
        $validated = $this->denominations->validate($breakdown, $amountMinor);

        return DB::transaction(function () use ($actor, $session, $amountMinor, $validated, $notes): CollectorCashDeclaration {
            $locked = CollectorCollectionSession::query()->lockForUpdate()->whereKey($session->getKey())->firstOrFail();

            if (! in_array($locked->status, [CollectorSessionStatus::Closed, CollectorSessionStatus::Rejected], true)) {
                throw new InvalidReconciliationOperation('Cash may only be declared for a closed or rejected session.');
            }

            $previous = CollectorCashDeclaration::query()
                ->where('collector_collection_session_id', $locked->getKey())
                ->where('status', CashDeclarationStatus::Active)
                ->lockForUpdate()
                ->first();

            $declaration = CollectorCashDeclaration::query()->create([
                'organisation_id' => $locked->organisation_id,
                'branch_id' => $locked->branch_id,
                'collector_collection_session_id' => $locked->getKey(),
                'declaration_number' => $this->numbers->next($locked->organisation, ReconciliationSequenceType::Declaration, (int) $locked->session_date->year),
                'declared_cash_minor' => $amountMinor,
                'currency_code' => $locked->currency_code,
                'denomination_breakdown' => $validated,
                'status' => CashDeclarationStatus::Active,
                'declared_at' => now(),
                'declared_by_user_id' => $actor->getKey(),
                'notes' => $notes,
                'active_session_key' => (string) $locked->getKey(),
            ]);

            if ($previous !== null) {
                $previous->update([
                    'status' => CashDeclarationStatus::Superseded,
                    'superseded_by_id' => $declaration->getKey(),
                    'active_session_key' => null,
                ]);
            }

            $locked->update(['declared_cash_minor' => $amountMinor]);

            return $declaration->refresh();
        }, attempts: 3);
    }
}
