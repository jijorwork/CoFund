<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Actions\RecordLoanRepayment;
use App\Domain\Loans\Actions\ReverseLoanRepayment;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoanFinancialRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRepaymentController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanRepaymentsView->value);
        $query = LoanRepayment::query()->where('organisation_id', $context->organisation()->getKey())->with('loan')->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status));

        return JsonResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(LoanFinancialRequest $request, string $loanUuid, TenantContext $context, RecordLoanRepayment $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $loan = Loan::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $loanUuid)->firstOrFail();
        $source = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('source_wallet_uuid'))->firstOrFail();

        return new JsonResource($action->handle($actor, $loan, $source, $request->validated('amount_minor'), $request->validated('method'), CarbonImmutable::parse($request->validated('date')), $request->idempotencyKey()));
    }

    public function show(string $repaymentUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanRepaymentsView->value);

        return new JsonResource($this->find($context, $repaymentUuid)->load(['loan', 'allocations.installment', 'financialTransaction']));
    }

    public function allocations(string $repaymentUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanRepaymentsView->value);

        return JsonResource::collection($this->find($context, $repaymentUuid)->allocations()->with('installment')->get());
    }

    public function reverse(LoanFinancialRequest $request, string $repaymentUuid, TenantContext $context, ReverseLoanRepayment $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return new JsonResource($action->handle($actor, $this->find($context, $repaymentUuid), $request->validated('reason'), $request->idempotencyKey()));
    }

    private function find(TenantContext $context, string $uuid): LoanRepayment
    {
        return LoanRepayment::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
