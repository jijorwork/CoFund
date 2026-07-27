<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Actions\CreateLedgerAccount;
use App\Domain\Accounting\Actions\UpdateLedgerAccount;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Queries\FindLedgerAccountByUuid;
use App\Domain\Accounting\Queries\ListLedgerAccounts;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LedgerAccountRequest;
use App\Http\Requests\Api\V1\ListLedgerAccountsRequest;
use App\Http\Resources\Api\V1\LedgerAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LedgerAccountController extends Controller
{
    public function index(ListLedgerAccountsRequest $request, ListLedgerAccounts $accounts): AnonymousResourceCollection
    {
        return LedgerAccountResource::collection($accounts->handle(
            $request->validated('filter', []),
            $request->validated('sort', 'code'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        LedgerAccountRequest $request,
        CreateLedgerAccount $create,
        FindLedgerAccountByUuid $accounts,
        TenantContext $context,
    ): LedgerAccountResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = collect($request->validated())->except(['branch_uuid', 'parent_uuid'])->all();

        return LedgerAccountResource::make($create->handle(
            $actor,
            $attributes,
            $this->branch($request->validated('branch_uuid'), $context),
            $this->parent($request->validated('parent_uuid'), $accounts),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(
        Request $request,
        string $ledgerAccountUuid,
        FindLedgerAccountByUuid $accounts,
        RoleAuthorization $authorization,
    ): LedgerAccountResource {
        $authorization->authorize(FoundationPermission::LedgerAccountsView->value);

        return LedgerAccountResource::make($accounts->handle($ledgerAccountUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function update(
        LedgerAccountRequest $request,
        string $ledgerAccountUuid,
        FindLedgerAccountByUuid $accounts,
        UpdateLedgerAccount $update,
        TenantContext $context,
    ): LedgerAccountResource {
        $account = $accounts->handle($ledgerAccountUuid) ?? abort(404);
        $branch = $request->has('branch_uuid')
            ? $this->branch($request->validated('branch_uuid'), $context)
            : $account->branch;
        $parent = $request->has('parent_uuid')
            ? $this->parent($request->validated('parent_uuid'), $accounts)
            : $account->parent;
        $attributes = collect($request->validated())->except(['branch_uuid', 'parent_uuid'])->all();

        return LedgerAccountResource::make($update->handle($account, $attributes, $branch, $parent))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        return $uuid === null ? null : Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }

    private function parent(?string $uuid, FindLedgerAccountByUuid $accounts): ?LedgerAccount
    {
        return $uuid === null ? null : $accounts->handle($uuid) ?? abort(404);
    }
}
