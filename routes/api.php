<?php

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Resources\Api\V1\TenantContextResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', function (Request $request) {
        return ApiResponse::success($request, [
            'status' => 'ok',
            'application' => config('app.name'),
            'api_version' => 'v1',
        ]);
    })->name('health');

    Route::middleware(['auth', 'tenant.context'])->group(function () {
        Route::get('context', function (Request $request, TenantContext $tenantContext) {
            return TenantContextResource::make($tenantContext)->additional([
                'meta' => ['request_id' => ApiResponse::requestId($request)],
            ]);
        })->name('context');
    });
});
