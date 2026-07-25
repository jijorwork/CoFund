<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(Request $request, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => self::requestId($request)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $details = [],
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => ['request_id' => self::requestId($request)],
        ], $status);
    }

    public static function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', '');
    }
}
