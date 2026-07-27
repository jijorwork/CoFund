<?php

namespace App\Http\Responses;

use App\Domain\Reports\ValueObjects\ReportDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportResponse
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $filters
     */
    public static function make(
        Request $request,
        string $report,
        mixed $data,
        array $summary,
        ReportDateRange $range,
        string $currencyCode,
        array $filters = [],
    ): JsonResponse {
        return response()->json([
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'request_id' => ApiResponse::requestId($request),
                'report' => $report,
                'currency_code' => $currencyCode,
                ...$range->metadata(),
                'filters' => $filters,
                'generated_at' => now()->utc()->toIso8601String(),
                'cached' => false,
            ],
            'links' => [],
        ]);
    }
}
