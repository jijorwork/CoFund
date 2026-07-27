<?php

use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Domain\Welfare\Services\CalculateWelfareWaitingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('installs the welfare persistence foundation', function (): void {
    foreach ([
        'welfare_products',
        'welfare_event_types',
        'welfare_enrolments',
        'welfare_events',
        'welfare_claims',
        'welfare_claim_terms',
        'welfare_claim_beneficiaries',
        'welfare_claim_approvals',
        'welfare_documents',
        'welfare_claim_liabilities',
        'welfare_benefit_payments',
        'welfare_claim_amendments',
        'welfare_number_sequences',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumn('receipts', 'welfare_benefit_payment_id'))->toBeTrue();
});

it('uses calendar-safe welfare waiting periods', function (): void {
    $product = new WelfareProduct(['waiting_period_count' => 1, 'waiting_period_unit' => 'months']);
    $result = app(CalculateWelfareWaitingPeriod::class)->endDate($product, CarbonImmutable::parse('2026-01-31'));

    expect($result?->toDateString())->toBe('2026-02-28');
});

it('registers welfare accounting and role vocabulary', function (): void {
    expect(AccountPurpose::WelfareClaimsPayable->value)->toBe('welfare_claims_payable')
        ->and(AccountPurpose::WelfareBenefitExpense->value)->toBe('welfare_benefit_expense')
        ->and(FinancialTransactionType::WelfareLiabilityRecognition->value)->toBe('welfare_liability_recognition')
        ->and(SystemRole::WelfareOfficer->value)->toBe('welfare_officer')
        ->and(SystemRole::Verifier->value)->toBe('verifier')
        ->and(FoundationPermission::WelfarePaymentsReverse->value)->toBe('welfare_payments.reverse');
});

it('registers the phase ten welfare api routes', function (): void {
    foreach ([
        'api.v1.welfare-products.index',
        'api.v1.welfare-enrolments.store',
        'api.v1.welfare-events.store',
        'api.v1.welfare-claims.submit',
        'api.v1.welfare-claims.approve',
        'api.v1.welfare-claims.liability.recognise',
        'api.v1.welfare-claims.payments.store',
        'api.v1.welfare-benefit-payments.reverse',
        'api.v1.welfare-claim-liabilities.reverse',
        'api.v1.members.welfare-summary',
        'api.v1.reports.welfare-outstanding-payables',
    ] as $name) {
        expect(Route::has($name))->toBeTrue();
    }
});

it('does not expose destructive welfare routes', function (): void {
    expect(Route::has('api.v1.welfare-products.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.welfare-claims.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.welfare-benefit-payments.destroy'))->toBeFalse();
});
