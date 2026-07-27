<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Actions\CreateExpenseRequest;
use App\Domain\Expenses\Actions\ManageExpenseApproval;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpenseDecisionRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseRequestController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsView->value);
        $query = ExpenseRequest::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->with(['category', 'requester', 'branch']);
        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return ExpenseRequestResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(StoreExpenseRequest $request, TenantContext $context, CreateExpenseRequest $action): ExpenseRequestResource
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $category = ExpenseCategory::query()->whereBelongsTo($context->organisation())->where('public_id', $data['category_uuid'])->firstOrFail();
        $branch = isset($data['branch_uuid']) ? Branch::query()->whereBelongsTo($context->organisation())->where('public_id', $data['branch_uuid'])->firstOrFail() : null;
        $items = [];
        foreach ($request->array('items') as $item) {
            if (! is_array($item)) {
                continue;
            }
            $categoryUuid = isset($item['category_uuid']) ? (string) $item['category_uuid'] : null;
            $ledgerAccountUuid = isset($item['ledger_account_uuid']) ? (string) $item['ledger_account_uuid'] : null;
            $items[] = [
                'description' => (string) $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit_amount_minor' => (int) $item['unit_amount_minor'],
                'category' => $categoryUuid !== null ? ExpenseCategory::query()->whereBelongsTo($context->organisation())->where('public_id', $categoryUuid)->firstOrFail() : null,
                'ledger_account' => $ledgerAccountUuid !== null ? LedgerAccount::query()->whereBelongsTo($context->organisation())->where('public_id', $ledgerAccountUuid)->firstOrFail() : null,
            ];
        }
        $model = $action->handle($actor, $branch, $category, array_intersect_key($data, array_flip(['title', 'purpose', 'beneficiary_type', 'beneficiary_name', 'needed_by_date', 'expense_date', 'payment_method_preference', 'notes'])), $items);

        return ExpenseRequestResource::make($model);
    }

    public function show(Request $request, string $expenseUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseRequestResource
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsView->value);

        return ExpenseRequestResource::make($this->find($context, $expenseUuid)->load($this->relations()));
    }

    public function update(StoreExpenseRequest $request, string $expenseUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseRequestResource
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsUpdate->value);
        $expense = $this->find($context, $expenseUuid);
        if ($expense->status->value !== 'draft' || $expense->requester_user_id !== $this->actor($request)->getKey()) {
            throw new InvalidExpenseOperation('Only an owned draft may be updated.');
        }$expense->update($request->safe()->only(['title', 'purpose', 'beneficiary_type', 'beneficiary_name', 'needed_by_date', 'expense_date', 'payment_method_preference', 'notes']));

        return $this->resource($expense->refresh());
    }

    public function submit(Request $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->submit($this->actor($request), $this->find($context, $expenseUuid)));
    }

    public function review(ExpenseDecisionRequest $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->review($this->actor($request), $this->find($context, $expenseUuid), $request->integer('amount_minor')));
    }

    public function approve(ExpenseDecisionRequest $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->approve($this->actor($request), $this->find($context, $expenseUuid), $request->integer('amount_minor')));
    }

    public function secondaryApprove(ExpenseDecisionRequest $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->approve($this->actor($request), $this->find($context, $expenseUuid), $request->integer('amount_minor'), true));
    }

    public function reject(ExpenseDecisionRequest $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->reject($this->actor($request), $this->find($context, $expenseUuid), (string) $request->validated('reason')));
    }

    public function cancel(ExpenseDecisionRequest $request, string $expenseUuid, TenantContext $context, ManageExpenseApproval $action): ExpenseRequestResource
    {
        return $this->resource($action->cancel($this->actor($request), $this->find($context, $expenseUuid), (string) $request->validated('reason')));
    }

    public function complete(Request $request, string $expenseUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseRequestResource
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsApprove->value);
        $expense = $this->find($context, $expenseUuid);
        if ($expense->status->value !== 'paid' || $expense->outstanding_amount_minor !== 0 || ($expense->category->requires_receipt && ! $expense->documents()->where('document_type', 'receipt')->exists())) {
            throw new InvalidExpenseOperation('The expense is not ready for completion.');
        }$expense->update(['status' => ExpenseRequestStatus::Completed, 'completed_at' => now(), 'completed_by_user_id' => $this->actor($request)->getKey()]);

        return $this->resource($expense->refresh());
    }

    private function find(TenantContext $context, string $uuid): ExpenseRequest
    {
        return ExpenseRequest::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->where('public_id', $uuid)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resource(ExpenseRequest $request): ExpenseRequestResource
    {
        return ExpenseRequestResource::make($request->load($this->relations()));
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['category', 'requester', 'branch', 'items.category', 'items.ledgerAccount', 'approvals'];
    }
}
