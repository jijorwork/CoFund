<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Queries\FindFinancialTransactionByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReverseFinancialTransactionRequest;
use App\Http\Resources\Api\V1\FinancialTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;

class ReverseFinancialTransactionController extends Controller
{
    public function __invoke(
        ReverseFinancialTransactionRequest $request,
        string $transactionUuid,
        FindFinancialTransactionByUuid $transactions,
        ReverseFinancialTransaction $reverse,
    ): FinancialTransactionResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $transaction = $transactions->handle($transactionUuid) ?? abort(404);

        return FinancialTransactionResource::make($reverse->handle(
            $transaction,
            $actor,
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
