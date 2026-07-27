<?php

use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Domain\Reconciliation\Enums\CashVarianceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashDeclaration;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Reconciliation\Services\CalculateCollectorCashVariance;
use App\Domain\Reconciliation\Services\ValidateDenominationBreakdown;
use Illuminate\Database\QueryException;

test('cash variance calculation distinguishes exact shortage and overage', function () {
    $calculator = app(CalculateCollectorCashVariance::class);

    expect($calculator->calculate(10000, 10000)->shortageMinor)->toBe(0)
        ->and($calculator->calculate(10000, 10000)->overageMinor)->toBe(0)
        ->and($calculator->calculate(10000, 8500)->shortageMinor)->toBe(1500)
        ->and($calculator->calculate(10000, 11500)->overageMinor)->toBe(1500);
});

test('denomination breakdown must use supported notes and exactly equal declaration', function () {
    $validator = app(ValidateDenominationBreakdown::class);

    expect($validator->validate([
        ['denomination_minor' => 5000, 'quantity' => 1],
        ['denomination_minor' => 10000, 'quantity' => 2],
    ], 25000))->toBe([
        ['denomination_minor' => 10000, 'quantity' => 2],
        ['denomination_minor' => 5000, 'quantity' => 1],
    ]);

    expect(fn () => $validator->validate([
        ['denomination_minor' => 5000, 'quantity' => 1],
    ], 10000))->toThrow(InvalidReconciliationOperation::class);
});

test('reconciliation factories preserve session ancestry and immutable declarations', function () {
    $session = CollectorCollectionSession::factory()->create();
    $declaration = CollectorCashDeclaration::factory()->create();

    expect($session->collectorWallet->organisation_id)->toBe($session->organisation_id)
        ->and($session->collectorWallet->walletable_id)->toBe($session->collector_user_id)
        ->and($declaration->session->organisation_id)->toBe($declaration->organisation_id)
        ->and($declaration->status)->toBe(CashDeclarationStatus::Active);

    expect(fn () => $declaration->update(['declared_cash_minor' => 999]))
        ->toThrow(LogicException::class);
});

test('only one active session is permitted for a collector wallet', function () {
    $session = CollectorCollectionSession::factory()->create();

    expect(fn () => CollectorCollectionSession::factory()->create([
        'organisation_id' => $session->organisation_id,
        'branch_id' => $session->branch_id,
        'collector_user_id' => $session->collector_user_id,
        'collector_wallet_id' => $session->collector_wallet_id,
        'active_wallet_key' => $session->active_wallet_key,
    ]))->toThrow(QueryException::class);
});

test('variance factories create an open shortage attached to the same tenant', function () {
    $variance = CollectorCashVariance::factory()->create();

    expect($variance->variance_type)->toBe(CashVarianceType::Shortage)
        ->and($variance->session->organisation_id)->toBe($variance->organisation_id)
        ->and($variance->collectorWallet->organisation_id)->toBe($variance->organisation_id);
});
