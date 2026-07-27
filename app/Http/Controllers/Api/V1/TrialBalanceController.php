<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Services\CalculateTrialBalance;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrialBalanceRequest;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TrialBalanceController extends Controller
{
    public function __invoke(
        TrialBalanceRequest $request,
        CalculateTrialBalance $trialBalance,
        TenantContext $context,
    ): JsonResponse {
        $asOf = $request->filled('as_of')
            ? CarbonImmutable::parse($request->validated('as_of'))
            : null;
        $branch = $request->filled('branch_uuid')
            ? Branch::query()
                ->whereBelongsTo($context->organisation())
                ->where('public_id', $request->validated('branch_uuid'))
                ->first() ?? abort(404)
            : null;

        return ApiResponse::success($request, $trialBalance->calculate($asOf, $branch));
    }
}
