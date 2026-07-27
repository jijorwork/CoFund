<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Services\BuildOrganisationDashboard;
use App\Domain\Reports\Services\ReportScope;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportQueryRequest;
use App\Http\Responses\ReportResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function organisation(
        ReportQueryRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildOrganisationDashboard $dashboard,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::DashboardsViewOrganisation->value);
        $range = $this->range($request, $context);
        $branch = $scope->branch($this->string($request, 'branch_uuid'));

        return ReportResponse::make(
            $request,
            'organisation_dashboard',
            $dashboard->build($range, $branch),
            [],
            $range,
            $context->organisation()->currency_code,
            $request->safe()->except(['per_page']),
        );
    }

    public function branch(
        ReportQueryRequest $request,
        string $branchUuid,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildOrganisationDashboard $dashboard,
        ReportScope $scope,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::DashboardsViewBranch->value);
        $range = $this->range($request, $context);
        $branch = $scope->branch($branchUuid);

        return ReportResponse::make(
            $request,
            'branch_dashboard',
            [
                ...$dashboard->build($range, $branch),
                'scope' => [
                    'branch_uuid' => $branch?->public_id,
                    'organisation_wide_wallets_excluded' => true,
                ],
            ],
            [],
            $range,
            $context->organisation()->currency_code,
            ['branch_uuid' => $branchUuid],
        );
    }

    public function role(
        ReportQueryRequest $request,
        string $dashboardKey,
        TenantContext $context,
        RoleAuthorization $authorization,
        BuildOrganisationDashboard $dashboard,
        ReportScope $scope,
    ): JsonResponse {
        $permission = match ($dashboardKey) {
            'treasurer', 'audit' => FoundationPermission::DashboardsViewFinancial,
            'collector' => FoundationPermission::DashboardsViewCollector,
            'loans' => FoundationPermission::DashboardsViewLoans,
            'welfare' => FoundationPermission::DashboardsViewWelfare,
            'withdrawals' => FoundationPermission::DashboardsViewWithdrawals,
            'membership' => FoundationPermission::DashboardsView,
            default => throw ValidationException::withMessages(['dashboard' => ['Unknown dashboard.']]),
        };
        $authorization->authorize($permission->value);
        $range = $this->range($request, $context);
        $branch = $scope->branch($this->string($request, 'branch_uuid'));
        $sections = $dashboard->build($range, $branch);
        $allowedSections = match ($dashboardKey) {
            'treasurer', 'audit' => ['accounting', 'expenses', 'loans', 'welfare', 'withdrawals'],
            'collector' => ['contributions'],
            'loans' => ['loans'],
            'welfare' => ['welfare'],
            'withdrawals' => ['withdrawals'],
            'membership' => ['membership', 'contributions'],
        };

        return ReportResponse::make(
            $request,
            $dashboardKey.'_dashboard',
            collect($sections)->only($allowedSections)->all(),
            [],
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
