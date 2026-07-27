<?php

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Reports\Services\BuildAgeingBuckets;
use App\Domain\Reports\Services\CalculateReportRatio;
use App\Domain\Reports\Services\ReportRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('installs report metadata tables', function (): void {
    expect(Schema::hasTable('saved_reports'))->toBeTrue()
        ->and(Schema::hasTable('report_exports'))->toBeTrue()
        ->and(Schema::hasTable('report_runs'))->toBeTrue();
});

it('registers unique report definitions with permissions and allow lists', function (): void {
    $definitions = app(ReportRegistry::class)->all();

    expect($definitions)->not->toBeEmpty()
        ->and(array_keys($definitions))->toHaveCount(count(array_unique(array_keys($definitions))))
        ->and($definitions['trial-balance']->permission)->toBe(FoundationPermission::ReportsFinancialView->value)
        ->and($definitions['trial-balance']->filters)->toContain('date_from', 'date_to', 'branch_uuid')
        ->and($definitions['trial-balance']->sorting)->not->toBeEmpty()
        ->and($definitions['trial-balance']->exportFormats)->toBe(['csv']);
});

it('calculates integer-safe report ratios and returns null for a zero denominator', function (): void {
    $ratios = app(CalculateReportRatio::class);

    expect($ratios->calculate(75, 100))->toBe([
        'numerator' => 75,
        'denominator' => 100,
        'ratio_basis_points' => 7500,
    ])->and($ratios->calculate(0, 0)['ratio_basis_points'])->toBeNull();
});

it('builds deterministic ageing buckets from an as-of date', function (): void {
    $buckets = app(BuildAgeingBuckets::class)->build([
        ['due_date' => '2026-07-27', 'amount_minor' => 100],
        ['due_date' => '2026-07-01', 'amount_minor' => 200],
        ['due_date' => '2026-05-01', 'amount_minor' => 300],
        ['due_date' => '2026-01-01', 'amount_minor' => 400],
    ], CarbonImmutable::parse('2026-07-27'));

    expect($buckets['current'])->toBe(['count' => 1, 'amount_minor' => 100])
        ->and($buckets['1_30_days'])->toBe(['count' => 1, 'amount_minor' => 200])
        ->and($buckets['61_90_days'])->toBe(['count' => 1, 'amount_minor' => 300])
        ->and($buckets['over_90_days'])->toBe(['count' => 1, 'amount_minor' => 400]);
});

it('registers dashboard financial statement saved report and export routes', function (): void {
    foreach ([
        'api.v1.dashboard',
        'api.v1.branches.dashboard',
        'api.v1.dashboards.role',
        'api.v1.reports.general-ledger',
        'api.v1.reports.trial-balance',
        'api.v1.reports.statement-of-financial-position',
        'api.v1.reports.income-and-expenditure',
        'api.v1.reports.cash-flow',
        'api.v1.saved-reports.index',
        'api.v1.saved-reports.run',
        'api.v1.report-exports.store',
        'api.v1.report-exports.download',
    ] as $route) {
        expect(Route::has($route))->toBeTrue();
    }
});

it('protects all report management APIs with authentication', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->getJson('/api/v1/reports/trial-balance')->assertUnauthorized();
    $this->getJson('/api/v1/saved-reports')->assertUnauthorized();
    $this->getJson('/api/v1/report-exports')->assertUnauthorized();
});
