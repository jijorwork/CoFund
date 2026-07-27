<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Domain\Reconciliation\Enums\CashHandoverStatus;
use App\Domain\Reconciliation\Enums\CashVarianceStatus;
use App\Domain\Reconciliation\Enums\CashVarianceType;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Enums\ReconciliationSequenceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashDeclaration;
use App\Domain\Reconciliation\Models\CollectorCashHandover;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\CalculateCollectorCashVariance;
use App\Domain\Reconciliation\Services\ReconciliationNumberGenerator;
use App\Domain\Reconciliation\Services\ReconciliationScope;
use App\Domain\Reconciliation\Services\ValidateDenominationBreakdown;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManageCollectorCashHandover
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private ReconciliationScope $scope,
        private ReconciliationNumberGenerator $numbers,
        private ValidateDenominationBreakdown $denominations,
        private CalculateCollectorCashVariance $variance,
        private PostFinancialTransaction $posting,
        private ReverseFinancialTransaction $reversals,
    ) {}

    public function create(User $actor, CollectorCollectionSession $session, Wallet $destination, string $idempotencyKey): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversCreate->value);
        $this->scope->assertCollectorActor($actor, $session->collector_user_id);
        $fingerprint = hash('sha256', implode('|', [$session->public_id, $destination->public_id]));

        return DB::transaction(function () use ($session, $destination, $idempotencyKey, $fingerprint): CollectorCashHandover {
            $existing = CollectorCashHandover::query()->whereBelongsTo($this->context->organisation())
                ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidReconciliationOperation('The idempotency key was reused with a different payload.');
                }

                return $existing;
            }

            $locked = CollectorCollectionSession::query()->lockForUpdate()->whereKey($session->getKey())->firstOrFail();
            $declaration = CollectorCashDeclaration::query()
                ->where('collector_collection_session_id', $locked->getKey())
                ->where('status', CashDeclarationStatus::Active)->first();
            if (! in_array($locked->status, [CollectorSessionStatus::Submitted, CollectorSessionStatus::UnderReview], true)
                || $declaration === null
                || $destination->organisation_id !== $locked->organisation_id
                || $destination->branch_id !== $locked->branch_id
                || $destination->wallet_type !== WalletType::OfficeCash
                || $destination->status !== WalletStatus::Active
                || $destination->currency_code !== $locked->currency_code) {
                throw new InvalidReconciliationOperation('The handover context is invalid.');
            }

            return CollectorCashHandover::query()->create([
                'organisation_id' => $locked->organisation_id,
                'branch_id' => $locked->branch_id,
                'collector_collection_session_id' => $locked->getKey(),
                'handover_number' => $this->numbers->next($locked->organisation, ReconciliationSequenceType::Handover, now()->year),
                'collector_user_id' => $locked->collector_user_id,
                'collector_wallet_id' => $locked->collector_wallet_id,
                'destination_wallet_id' => $destination->getKey(),
                'declared_cash_minor' => $declaration->declared_cash_minor,
                'currency_code' => $locked->currency_code,
                'status' => CashHandoverStatus::Draft,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
            ]);
        }, attempts: 3);
    }

    public function submit(User $actor, CollectorCashHandover $handover): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversCreate->value);
        $this->scope->assertCollectorActor($actor, $handover->collector_user_id);
        if ($handover->status !== CashHandoverStatus::Draft) {
            throw new InvalidReconciliationOperation('Only a draft handover can be submitted.');
        }
        $handover->update(['status' => CashHandoverStatus::Submitted, 'handed_over_at' => now(), 'submitted_by_user_id' => $actor->getKey()]);

        return $handover->refresh();
    }

    /** @param list<array{denomination_minor:int, quantity:int}> $breakdown */
    public function receive(User $actor, CollectorCashHandover $handover, int $acceptedMinor, array $breakdown = []): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversReceive->value);
        if ($handover->status !== CashHandoverStatus::Submitted || $handover->submitted_by_user_id === $actor->getKey()) {
            throw new InvalidReconciliationOperation('Cash receipt requires a different actor.');
        }
        if ($breakdown !== []) {
            $this->denominations->validate($breakdown, $acceptedMinor);
        }
        $variance = $this->variance->calculate($handover->session->expected_cash_minor, $acceptedMinor);
        $handover->update([
            'status' => CashHandoverStatus::Received,
            'accepted_cash_minor' => $acceptedMinor,
            'shortage_minor' => $variance->shortageMinor,
            'overage_minor' => $variance->overageMinor,
            'receiving_user_id' => $actor->getKey(),
            'received_by_user_id' => $actor->getKey(),
            'received_at' => now(),
        ]);

        return $handover->refresh();
    }

    public function approve(User $actor, CollectorCashHandover $handover): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversApprove->value);

        return DB::transaction(function () use ($actor, $handover): CollectorCashHandover {
            $locked = CollectorCashHandover::query()->with(['session', 'collectorWallet.ledgerAccount', 'destinationWallet.ledgerAccount', 'branch'])
                ->lockForUpdate()->whereKey($handover->getKey())->firstOrFail();
            if ($locked->status !== CashHandoverStatus::Received
                || in_array($actor->getKey(), [$locked->submitted_by_user_id, $locked->received_by_user_id], true)) {
                throw new InvalidReconciliationOperation('Handover approval requires an independent actor.');
            }

            $expected = $locked->session->expected_cash_minor;
            $accepted = $locked->accepted_cash_minor;
            $entries = [
                new TransactionEntryData($locked->destinationWallet->ledgerAccount, EntryType::Debit, $accepted, 'Cash received at office', null, $locked->destinationWallet),
                new TransactionEntryData($locked->collectorWallet->ledgerAccount, EntryType::Credit, min($expected, $accepted), 'Collector cash handed over', null, $locked->collectorWallet),
            ];
            if ($accepted > $expected) {
                $account = LedgerAccount::query()->whereBelongsTo($this->context->organisation())
                    ->where('purpose', AccountPurpose::CashOverageLiability)->whereNull('branch_id')->firstOrFail();
                $entries[] = new TransactionEntryData($account, EntryType::Credit, $accepted - $expected, 'Unidentified collector cash overage');
            }

            $transaction = $this->posting->handle($actor, new TransactionData(
                branch: $locked->branch,
                transactionType: FinancialTransactionType::CollectorCashHandover,
                currencyCode: $locked->currency_code,
                transactionDate: CarbonImmutable::today(),
                description: 'Collector cash handover '.$locked->handover_number,
                idempotencyKey: 'cash-handover:'.$locked->idempotency_key,
                referenceType: FinancialReferenceType::CollectorCashHandover,
                referenceId: $locked->public_id,
            ), $entries, FoundationPermission::CashHandoversApprove->value);

            $locked->update(['status' => CashHandoverStatus::Approved, 'financial_transaction_id' => $transaction->getKey(), 'approved_by_user_id' => $actor->getKey(), 'approved_at' => now()]);
            $locked->session->update(['status' => CollectorSessionStatus::Approved, 'approved_by_user_id' => $actor->getKey(), 'approved_at' => now()]);

            if ($locked->shortage_minor > 0 || $locked->overage_minor > 0) {
                CollectorCashVariance::query()->create([
                    'organisation_id' => $locked->organisation_id,
                    'branch_id' => $locked->branch_id,
                    'collector_collection_session_id' => $locked->collector_collection_session_id,
                    'collector_cash_handover_id' => $locked->getKey(),
                    'collector_user_id' => $locked->collector_user_id,
                    'collector_wallet_id' => $locked->collector_wallet_id,
                    'variance_type' => $locked->shortage_minor > 0 ? CashVarianceType::Shortage : CashVarianceType::Overage,
                    'amount_minor' => max($locked->shortage_minor, $locked->overage_minor),
                    'currency_code' => $locked->currency_code,
                    'status' => CashVarianceStatus::Open,
                    'reported_at' => now(),
                    'reported_by_user_id' => $actor->getKey(),
                ]);
            }

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, CollectorCashHandover $handover, string $reason): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversReject->value);
        if (! in_array($handover->status, [CashHandoverStatus::Submitted, CashHandoverStatus::Received], true)
            || $handover->submitted_by_user_id === $actor->getKey() || trim($reason) === '') {
            throw new InvalidReconciliationOperation('The handover cannot be rejected.');
        }
        $handover->update(['status' => CashHandoverStatus::Rejected, 'rejection_reason' => $reason]);

        return $handover->refresh();
    }

    public function reverse(User $actor, CollectorCashHandover $handover, string $reason, string $idempotencyKey): CollectorCashHandover
    {
        $this->authorization->authorize(FoundationPermission::CashHandoversReverse->value);
        if ($handover->status !== CashHandoverStatus::Approved || $handover->approved_by_user_id === $actor->getKey()) {
            throw new InvalidReconciliationOperation('The approved handover requires independent reversal.');
        }
        $transaction = $this->reversals->handle($handover->financialTransaction, $actor, $reason, 'cash-handover-reversal:'.$idempotencyKey, FoundationPermission::CashHandoversReverse->value);
        $handover->update(['status' => CashHandoverStatus::Reversed, 'reversal_reason' => $reason]);
        $handover->session->update(['status' => CollectorSessionStatus::UnderReview, 'approved_at' => null, 'approved_by_user_id' => null]);
        $handover->variances()->where('status', CashVarianceStatus::Open)->update(['status' => CashVarianceStatus::Reversed]);
        $handover->setRelation('financialTransaction', $transaction);

        return $handover->refresh();
    }
}
