<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Enums\ReconciliationSequenceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\ReconciliationNumberGenerator;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OpenCollectorCollectionSession
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private ReconciliationNumberGenerator $numbers,
    ) {}

    public function handle(
        User $actor,
        User $collector,
        Wallet $wallet,
        CarbonImmutable $sessionDate,
        ?string $notes = null,
    ): CollectorCollectionSession {
        $this->authorization->authorize(FoundationPermission::CollectionSessionsOpen->value);
        $this->scope->assertCollectorActor($actor, (int) $collector->getKey());
        $this->scope->assertBranch($wallet->branch_id);
        $organisation = $this->context->organisation();

        if (
            $wallet->organisation_id !== $organisation->getKey()
            || $wallet->wallet_type !== WalletType::CollectorCash
            || $wallet->status !== WalletStatus::Active
            || $wallet->walletable_type !== WalletOwnerType::CollectorUser->value
            || $wallet->walletable_id !== $collector->getKey()
            || $sessionDate->isAfter(CarbonImmutable::now($organisation->timezone)->startOfDay())
        ) {
            throw new InvalidReconciliationOperation('The collector wallet cannot open this session.');
        }

        return DB::transaction(function () use ($actor, $collector, $wallet, $sessionDate, $notes, $organisation): CollectorCollectionSession {
            if (CollectorCollectionSession::query()
                ->where('active_wallet_key', CollectorCollectionSession::activeWalletKey((int) $wallet->getKey()))
                ->lockForUpdate()
                ->exists()) {
                throw new InvalidReconciliationOperation('The collector wallet already has an open session.');
            }

            return CollectorCollectionSession::query()->create([
                'organisation_id' => $organisation->getKey(),
                'branch_id' => $wallet->branch_id,
                'collector_user_id' => $collector->getKey(),
                'collector_wallet_id' => $wallet->getKey(),
                'session_number' => $this->numbers->next(
                    $organisation,
                    ReconciliationSequenceType::Session,
                    $sessionDate->year,
                ),
                'session_date' => $sessionDate->toDateString(),
                'opened_at' => now(),
                'status' => CollectorSessionStatus::Open,
                'currency_code' => $wallet->currency_code,
                'opened_by_user_id' => $actor->getKey(),
                'notes' => $notes,
                'active_wallet_key' => CollectorCollectionSession::activeWalletKey((int) $wallet->getKey()),
            ])->load(['collector', 'collectorWallet', 'branch']);
        }, attempts: 3);
    }
}
