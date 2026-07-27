<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Queries\FindFinancialTransactionByUuid;
use App\Domain\Accounting\Queries\FindLedgerAccountByUuid;
use App\Domain\Accounting\Queries\ListFinancialTransactions;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFinancialTransactionsRequest;
use App\Http\Requests\Api\V1\PostFinancialTransactionRequest;
use App\Http\Resources\Api\V1\FinancialTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialTransactionController extends Controller
{
    public function index(ListFinancialTransactionsRequest $request, ListFinancialTransactions $transactions): AnonymousResourceCollection
    {
        return FinancialTransactionResource::collection($transactions->handle(
            $request->validated('filter', []),
            $request->validated('sort', '-transaction_date'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        PostFinancialTransactionRequest $request,
        PostFinancialTransaction $post,
        FindLedgerAccountByUuid $accounts,
        FindWalletByUuid $wallets,
        FindMemberByUuid $members,
        TenantContext $context,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $entries = [];

        foreach ($request->safe()->array('entries') as $entry) {
            if (! is_array($entry)) {
                abort(422);
            }

            $memberUuid = isset($entry['member_uuid']) ? (string) $entry['member_uuid'] : null;
            $walletUuid = isset($entry['wallet_uuid']) ? (string) $entry['wallet_uuid'] : null;
            $entries[] = new TransactionEntryData(
                ledgerAccount: $accounts->handle((string) $entry['account_uuid']) ?? abort(404),
                entryType: EntryType::from((string) $entry['entry_type']),
                amountMinor: (int) $entry['amount_minor'],
                description: isset($entry['description']) ? (string) $entry['description'] : null,
                member: $memberUuid !== null
                    ? $members->handle($memberUuid) ?? abort(404)
                    : null,
                wallet: $walletUuid !== null
                    ? $wallets->handle($walletUuid) ?? abort(404)
                    : null,
            );
        }
        $data = new TransactionData(
            branch: $this->branch($request->validated('branch_uuid'), $context),
            transactionType: FinancialTransactionType::from($request->validated('transaction_type')),
            currencyCode: $request->validated('currency_code'),
            transactionDate: CarbonImmutable::parse($request->validated('transaction_date')),
            description: $request->validated('description'),
            idempotencyKey: $request->validated('idempotency_key'),
            externalReference: $request->validated('external_reference'),
            referenceType: $request->filled('reference_type')
                ? FinancialReferenceType::from($request->validated('reference_type'))
                : null,
            referenceId: $request->validated('reference_uuid'),
            metadata: $request->validated('metadata'),
        );
        $resource = FinancialTransactionResource::make($post->handle($actor, $data, $entries))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);

        return $resource->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $transactionUuid,
        FindFinancialTransactionByUuid $transactions,
        RoleAuthorization $authorization,
    ): FinancialTransactionResource {
        $authorization->authorize(FoundationPermission::TransactionsView->value);

        return FinancialTransactionResource::make($transactions->handle($transactionUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        return $uuid === null ? null : Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
