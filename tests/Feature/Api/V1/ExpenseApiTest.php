<?php

use Illuminate\Support\Facades\Route;

test('expense and petty cash lifecycle routes are registered', function () {
    $names = collect(Route::getRoutes()->getRoutes())->pluck('action.as')->filter();
    expect($names)->toContain(
        'api.v1.expense-categories.index',
        'api.v1.expense-requests.submit',
        'api.v1.expense-requests.secondary-approve',
        'api.v1.expense-requests.payments.store',
        'api.v1.expense-payments.reverse',
        'api.v1.petty-cash-advances.disburse',
        'api.v1.petty-cash-retirements.approve',
        'api.v1.reports.outstanding-advances',
    );
});

test('expense APIs reject unauthenticated requests', function () {
    $this->getJson('/api/v1/expense-categories')->assertUnauthorized();
    $this->getJson('/api/v1/expense-requests')->assertUnauthorized();
    $this->getJson('/api/v1/expense-payments')->assertUnauthorized();
    $this->getJson('/api/v1/petty-cash-advances')->assertUnauthorized();
    $this->getJson('/api/v1/reports/expenses')->assertUnauthorized();
});
