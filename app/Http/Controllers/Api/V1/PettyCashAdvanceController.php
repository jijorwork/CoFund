<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Actions\ManagePettyCashAdvance;
use App\Domain\Expenses\Actions\ManagePettyCashRetirement;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpenseDecisionRequest;
use App\Http\Requests\Api\V1\StorePettyCashAdvanceRequest;
use App\Http\Requests\Api\V1\StorePettyCashRetirementRequest;
use App\Http\Resources\Api\V1\PettyCashAdvanceResource;
use App\Http\Resources\Api\V1\PettyCashRetirementResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PettyCashAdvanceController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::PettyCashView->value);

        return PettyCashAdvanceResource::collection(PettyCashAdvance::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(StorePettyCashAdvanceRequest $request, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        $d = $request->validated();
        $branch = isset($d['branch_uuid']) ? Branch::query()->whereBelongsTo($context->organisation())->where('public_id', $d['branch_uuid'])->firstOrFail() : null;
        $custodian = User::query()->where('public_id', $d['custodian_user_uuid'])->firstOrFail();
        $recipient = User::query()->where('public_id', $d['recipient_user_uuid'])->firstOrFail();
        $petty = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $d['petty_cash_wallet_uuid'])->firstOrFail();
        $funding = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $d['funding_wallet_uuid'])->firstOrFail();

        return PettyCashAdvanceResource::make($action->create($this->actor($request), $branch, $custodian, $recipient, $petty, $funding, $d['requested_amount_minor'], $d['purpose'], isset($d['retirement_due_date']) ? CarbonImmutable::parse($d['retirement_due_date']) : null));
    }

    public function show(Request $request, string $advanceUuid, TenantContext $context, RoleAuthorization $authorization): PettyCashAdvanceResource
    {
        $authorization->authorize(FoundationPermission::PettyCashView->value);

        return PettyCashAdvanceResource::make($this->find($context, $advanceUuid));
    }

    public function submit(Request $request, string $advanceUuid, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        return PettyCashAdvanceResource::make($action->submit($this->actor($request), $this->find($context, $advanceUuid)));
    }

    public function approve(ExpenseDecisionRequest $request, string $advanceUuid, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        return PettyCashAdvanceResource::make($action->approve($this->actor($request), $this->find($context, $advanceUuid), $request->integer('amount_minor')));
    }

    public function reject(ExpenseDecisionRequest $request, string $advanceUuid, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        return PettyCashAdvanceResource::make($action->reject($this->actor($request), $this->find($context, $advanceUuid), (string) $request->validated('reason')));
    }

    public function disburse(Request $request, string $advanceUuid, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422);

        return PettyCashAdvanceResource::make($action->disburse($this->actor($request), $this->find($context, $advanceUuid), $key));
    }

    public function retire(StorePettyCashRetirementRequest $request, string $advanceUuid, TenantContext $context, ManagePettyCashRetirement $action): PettyCashAdvanceResource
    {
        $d = $request->validated();
        $advance = $this->find($context, $advanceUuid);
        $items = [];
        foreach ($request->array('items') as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'category' => ExpenseCategory::query()->whereBelongsTo($context->organisation())->where('public_id', (string) $item['category_uuid'])->firstOrFail(),
                'ledger_account' => LedgerAccount::query()->whereBelongsTo($context->organisation())->where('public_id', (string) $item['ledger_account_uuid'])->firstOrFail(),
                'description' => (string) $item['description'],
                'amount_minor' => (int) $item['amount_minor'],
                'expense_date' => CarbonImmutable::parse((string) $item['expense_date']),
            ];
        }
        $action->create($this->actor($request), $advance, $items, (int) $d['returned_cash_minor']);

        return PettyCashAdvanceResource::make($advance->refresh());
    }

    public function retirements(Request $request, string $advanceUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::PettyCashView->value);

        return PettyCashRetirementResource::collection($this->find($context, $advanceUuid)->retirements()->with('items')->latest()->paginate(25));
    }

    public function reverse(ExpenseDecisionRequest $request, string $advanceUuid, TenantContext $context, ManagePettyCashAdvance $action): PettyCashAdvanceResource
    {
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422);

        return PettyCashAdvanceResource::make($action->reverse($this->actor($request), $this->find($context, $advanceUuid), (string) $request->validated('reason'), $key));
    }

    private function find(TenantContext $context, string $uuid): PettyCashAdvance
    {
        return PettyCashAdvance::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->where('public_id', $uuid)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
