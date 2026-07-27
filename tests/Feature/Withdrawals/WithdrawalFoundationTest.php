<?php

use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Services\CalculateWithdrawalFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('installs the withdrawal persistence foundation', function (): void {
    foreach (['withdrawal_products', 'withdrawal_reasons', 'savings_holds', 'withdrawal_requests', 'withdrawal_request_terms', 'withdrawal_approvals', 'withdrawal_payouts', 'withdrawal_request_amendments', 'withdrawal_number_sequences'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(Schema::hasColumn('receipts', 'withdrawal_payout_id'))->toBeTrue();
});

it('calculates deducted percentage fees with integer basis points', function (): void {
    $product = new WithdrawalProduct(['fee_type' => 'percentage', 'fee_value' => 125, 'fee_treatment' => 'deducted_from_payout']);
    $result = app(CalculateWithdrawalFee::class)->calculate($product, 10001);

    expect($result['fee_minor'])->toBe(125)
        ->and($result['net_payout_minor'])->toBe(9876);
});

it('rejects a fee greater than the gross withdrawal', function (): void {
    $product = new WithdrawalProduct(['fee_type' => 'fixed', 'fee_value' => 10001, 'fee_treatment' => 'deducted_from_payout']);

    expect(fn () => app(CalculateWithdrawalFee::class)->calculate($product, 10000))->toThrow(InvalidWithdrawalOperation::class);
});

it('registers withdrawal accounting permissions and routes', function (): void {
    expect(AccountPurpose::WithdrawalFeeIncome->value)->toBe('withdrawal_fee_income')
        ->and(ReceiptType::WithdrawalPayout->value)->toBe('withdrawal_payout')
        ->and(FoundationPermission::WithdrawalPayoutsReverse->value)->toBe('withdrawal_payouts.reverse');
    foreach (['api.v1.withdrawal-products.index', 'api.v1.savings-holds.index', 'api.v1.members.withdrawable-savings', 'api.v1.withdrawal-requests.submit', 'api.v1.withdrawal-requests.payouts.store', 'api.v1.withdrawal-payouts.reverse', 'api.v1.reports.outstanding-withdrawals'] as $route) {
        expect(Route::has($route))->toBeTrue();
    }
});

it('does not expose destructive withdrawal routes', function (): void {
    expect(Route::has('api.v1.withdrawal-products.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.withdrawal-requests.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.withdrawal-payouts.destroy'))->toBeFalse();
});
