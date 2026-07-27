<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Services\BuildFinancialStatements;
use App\Domain\Reports\Services\BuildGeneralLedgerReport;
use App\Domain\Reports\Services\BuildTrialBalanceReport;
use App\Domain\Reports\Services\ReportScope;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportQueryRequest;
use App\Http\Responses\ReportResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FinancialReportController extends Controller
{
    public function generalLedger(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildGeneralLedgerReport $report,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReportsFinancialView->value);
        $range = $this->range($request, $context);
        $result = $report->build($range, $scope->branch($this->string($request, 'branch_uuid')), $this->string($request, 'ledger_account_uuid'));

        return $this->respond($request, 'general_ledger', $result, $range, $context);
    }

    public function trialBalance(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildTrialBalanceReport $report,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReportsFinancialView->value);
        $range = $this->range($request, $context);
        $result = $report->build($range, $scope->branch($this->string($request, 'branch_uuid')), $request->boolean('include_zero_balances'));

        return $this->respond($request, 'trial_balance', $result, $range, $context);
    }

    public function financialPosition(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildFinancialStatements $report,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReportsFinancialView->value);
        $range = $this->range($request, $context);
        $result = $report->financialPosition($range, $scope->branch($this->string($request, 'branch_uuid')));

        return $this->respond($request, 'statement_of_financial_position', $result, $range, $context);
    }

    public function incomeAndExpenditure(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildFinancialStatements $report,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReportsFinancialView->value);
        $range = $this->range($request, $context);
        $result = $report->incomeAndExpenditure($range, $scope->branch($this->string($request, 'branch_uuid')));

        return $this->respond($request, 'income_and_expenditure', $result, $range, $context);
    }

    public function cashFlow(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildFinancialStatements $report,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReportsFinancialView->value);
        $range = $this->range($request, $context);
        $result = $report->cashFlow($range, $scope->branch($this->string($request, 'branch_uuid')));

        return $this->respond($request, 'cash_flow', $result, $range, $context);
    }

    /** @param array{data: mixed, summary: array<string, mixed>} $result */
    private function respond(ReportQueryRequest $request, string $key, array $result, ReportDateRange $range, TenantContext $context): JsonResponse
    {
        return ReportResponse::make(
            $request,
            $key,
            $result['data'],
            $result['summary'],
            $range,
            $context->organisation()->currency_code,
            $request->safe()->except(['per_page']),
        );
    }

    private function range(ReportQueryRequest $request, TenantContext $context): ReportDateRange
    {
        try {
            return ReportDateRange::from(
                $context->organisation(),
                $this->string($request, 'date_from'),
                $this->string($request, 'date_to'),
                $this->string($request, 'as_of'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['date_range' => [$exception->getMessage()]]);
        }
    }

    private function string(ReportQueryRequest $request, string $key): ?string
    {
        $value = $request->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
