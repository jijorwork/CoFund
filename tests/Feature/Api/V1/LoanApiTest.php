<?php

use Illuminate\Support\Facades\Route;

test('loan lifecycle and reporting routes are registered', function () {
    $names = collect(Route::getRoutes()->getRoutes())->pluck('action.as')->filter();

    expect($names)->toContain(
        'api.v1.loan-products.index',
        'api.v1.loan-applications.submit',
        'api.v1.loan-applications.cancel',
        'api.v1.loan-applications.secondary-approve',
        'api.v1.loan-applications.guarantors.index',
        'api.v1.loan-guarantors.revoke',
        'api.v1.loan-collateral.release',
        'api.v1.loan-applications.offers.index',
        'api.v1.loan-applications.offers.store',
        'api.v1.loan-offers.accept',
        'api.v1.loan-offers.decline',
        'api.v1.loans.disburse',
        'api.v1.loans.repayments.store',
        'api.v1.loans.settle',
        'api.v1.loan-repayments.reverse',
        'api.v1.reports.loan-portfolio',
        'api.v1.reports.loan-arrears',
        'api.v1.reports.guarantor-exposure',
    );
});

test('loan APIs reject unauthenticated requests', function () {
    $this->getJson('/api/v1/loan-products')->assertUnauthorized();
    $this->getJson('/api/v1/loan-applications')->assertUnauthorized();
    $this->getJson('/api/v1/loans')->assertUnauthorized();
    $this->getJson('/api/v1/loan-repayments')->assertUnauthorized();
    $this->getJson('/api/v1/reports/loan-portfolio')->assertUnauthorized();
});
