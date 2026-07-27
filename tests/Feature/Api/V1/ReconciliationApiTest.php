<?php

use Illuminate\Support\Facades\Route;

test('reconciliation API exposes every planned lifecycle and report route', function () {
    $names = collect(Route::getRoutes()->getRoutes())->pluck('action.as')->filter();

    expect($names)->toContain(
        'api.v1.collector-sessions.index',
        'api.v1.collector-sessions.declare-cash',
        'api.v1.cash-handovers.approve',
        'api.v1.cash-handovers.reverse',
        'api.v1.cash-variances.resolve',
        'api.v1.cash-variances.write-off',
        'api.v1.cash-deposits.confirm',
        'api.v1.cash-deposits.reverse',
        'api.v1.reports.cash-position',
    );
});

test('reconciliation endpoints require authentication', function () {
    $this->getJson('/api/v1/collector-sessions')->assertUnauthorized();
    $this->getJson('/api/v1/cash-handovers')->assertUnauthorized();
    $this->getJson('/api/v1/cash-variances')->assertUnauthorized();
    $this->getJson('/api/v1/cash-deposits')->assertUnauthorized();
    $this->getJson('/api/v1/reports/cash-position')->assertUnauthorized();
});
