<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Actions\CreateExpenseCategory;
use App\Domain\Expenses\Enums\ExpenseCategoryStatus;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExpenseCategoryRequest;
use App\Http\Resources\Api\V1\ExpenseCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ExpenseCategoriesView->value);
        $query = ExpenseCategory::query()->whereBelongsTo($context->organisation())->with(['parent', 'defaultLedgerAccount']);
        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return ExpenseCategoryResource::collection($query->orderBy($request->input('sort') === 'name' ? 'name' : 'code')->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(StoreExpenseCategoryRequest $request, TenantContext $context, CreateExpenseCategory $action): ExpenseCategoryResource
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $account = LedgerAccount::query()->whereBelongsTo($context->organisation())->where('public_id', $data['default_ledger_account_uuid'])->firstOrFail();
        $parent = isset($data['parent_uuid']) ? ExpenseCategory::query()->whereBelongsTo($context->organisation())->where('public_id', $data['parent_uuid'])->firstOrFail() : null;
        $category = $action->handle($actor, $account, array_diff_key($data, array_flip(['parent_uuid', 'default_ledger_account_uuid'])), $parent);

        return ExpenseCategoryResource::make($category->load(['parent', 'defaultLedgerAccount']));
    }

    public function show(Request $request, string $categoryUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseCategoryResource
    {
        $authorization->authorize(FoundationPermission::ExpenseCategoriesView->value);

        return ExpenseCategoryResource::make($this->find($context, $categoryUuid)->load(['parent', 'defaultLedgerAccount']));
    }

    public function update(StoreExpenseCategoryRequest $request, string $categoryUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseCategoryResource
    {
        $authorization->authorize(FoundationPermission::ExpenseCategoriesUpdate->value);
        $category = $this->find($context, $categoryUuid);
        $category->update($request->safe()->except(['parent_uuid', 'default_ledger_account_uuid']));

        return ExpenseCategoryResource::make($category->refresh()->load(['parent', 'defaultLedgerAccount']));
    }

    public function suspend(Request $request, string $categoryUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseCategoryResource
    {
        return $this->status($request, $this->find($context, $categoryUuid), $authorization, ExpenseCategoryStatus::Suspended);
    }

    public function reinstate(Request $request, string $categoryUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseCategoryResource
    {
        return $this->status($request, $this->find($context, $categoryUuid), $authorization, ExpenseCategoryStatus::Active);
    }

    public function archive(Request $request, string $categoryUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseCategoryResource
    {
        $authorization->authorize(FoundationPermission::ExpenseCategoriesArchive->value);
        $category = $this->find($context, $categoryUuid);
        $category->update(['status' => ExpenseCategoryStatus::Archived]);

        return ExpenseCategoryResource::make($category->refresh());
    }

    private function status(Request $request, ExpenseCategory $category, RoleAuthorization $authorization, ExpenseCategoryStatus $status): ExpenseCategoryResource
    {
        $authorization->authorize(FoundationPermission::ExpenseCategoriesUpdate->value);
        $category->update(['status' => $status]);

        return ExpenseCategoryResource::make($category->refresh());
    }

    private function find(TenantContext $context, string $uuid): ExpenseCategory
    {
        return ExpenseCategory::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)->firstOrFail();
    }
}
