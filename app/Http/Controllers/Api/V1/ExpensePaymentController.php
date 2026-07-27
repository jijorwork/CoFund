<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Expenses\Actions\PayApprovedExpense;
use App\Domain\Expenses\Actions\ReverseExpensePayment;
use App\Domain\Expenses\Enums\ExpensePaymentMethod;
use App\Domain\Expenses\Enums\ExpensePaymentType;
use App\Domain\Expenses\Models\ExpensePayment;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpenseDecisionRequest;
use App\Http\Requests\Api\V1\PayExpenseRequest;
use App\Http\Resources\Api\V1\ExpensePaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpensePaymentController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ExpensePaymentsView->value);
        $query = ExpensePayment::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->with($this->relations());

        return ExpensePaymentResource::collection($query->latest('payment_date')->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(PayExpenseRequest $request, string $expenseUuid, TenantContext $context, PayApprovedExpense $action): ExpensePaymentResource
    {
        $data = $request->validated();
        $expense = ExpenseRequest::query()->whereBelongsTo($context->organisation())->where('public_id', $expenseUuid)->firstOrFail();
        $wallet = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $data['source_wallet_uuid'])->with('ledgerAccount')->firstOrFail();
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');

        return ExpensePaymentResource::make($action->handle($this->actor($request), $expense, $wallet, $data['amount_minor'], ExpensePaymentType::from($data['payment_type']), ExpensePaymentMethod::from($data['payment_method']), CarbonImmutable::parse($data['payment_date']), $data['payee_name'], $key));
    }

    public function show(Request $request, string $paymentUuid, TenantContext $context, RoleAuthorization $authorization): ExpensePaymentResource
    {
        $authorization->authorize(FoundationPermission::ExpensePaymentsView->value);

        return ExpensePaymentResource::make($this->find($context, $paymentUuid)->load($this->relations()));
    }

    public function reverse(ExpenseDecisionRequest $request, string $paymentUuid, TenantContext $context, ReverseExpensePayment $action): ExpensePaymentResource
    {
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');

        return ExpensePaymentResource::make($action->handle($this->actor($request), $this->find($context, $paymentUuid), (string) $request->validated('reason'), $key)->load($this->relations()));
    }

    private function find(TenantContext $context, string $uuid): ExpensePayment
    {
        return ExpensePayment::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->where('public_id', $uuid)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['expenseRequest', 'sourceWallet', 'financialTransaction'];
    }
}
