<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Actions\DisburseApprovedLoan;
use App\Domain\Loans\Actions\RecordLoanRepayment;
use App\Domain\Loans\Actions\ReverseLoanDisbursement;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanDisbursement;
use App\Domain\Loans\Services\CalculateLoanSettlementQuote;
use App\Domain\Loans\Services\RecalculateLoanPosition;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoanFinancialRequest;
use App\Http\Resources\Api\V1\LoanResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoansView->value);
        $query = Loan::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'product'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))->when($request->input('filter.performance_status'), fn ($query, $status) => $query->where('performance_status', $status));

        return LoanResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(string $loanUuid, TenantContext $context, RoleAuthorization $authorization): LoanResource
    {
        $authorization->authorize(FoundationPermission::LoansView->value);

        return LoanResource::make($this->find($context, $loanUuid)->load(['member', 'product', 'application', 'offer', 'disbursement']));
    }

    public function schedule(string $loanUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoansView->value);

        return JsonResource::collection($this->find($context, $loanUuid)->installments()->get());
    }

    public function position(Request $request, string $loanUuid, TenantContext $context, RoleAuthorization $authorization, RecalculateLoanPosition $positions): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoansView->value);

        return new JsonResource($positions->recalculate($this->find($context, $loanUuid), CarbonImmutable::now($context->organisation()->timezone)->startOfDay()));
    }

    public function settlementQuote(string $loanUuid, TenantContext $context, CalculateLoanSettlementQuote $quotes): JsonResource
    {
        return new JsonResource($quotes->calculate($this->find($context, $loanUuid), CarbonImmutable::now($context->organisation()->timezone)->startOfDay()));
    }

    public function settle(LoanFinancialRequest $request, string $loanUuid, TenantContext $context, CalculateLoanSettlementQuote $quotes, RecordLoanRepayment $repayments): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $loan = $this->find($context, $loanUuid);
        $date = CarbonImmutable::parse($request->validated('date'));
        $quote = $quotes->calculate($loan, $date);

        if ($quote['settlement_discount_minor'] !== 0 || $quote['settlement_fee_minor'] !== 0) {
            throw new InvalidLoanOperation('Configured settlement adjustments are not supported by the first-version repayment allocator.');
        }
        if ((int) $request->validated('amount_minor') !== $quote['total_settlement_minor']) {
            throw new InvalidLoanOperation('The settlement amount must match the current settlement quote.');
        }

        $source = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('source_wallet_uuid'))->firstOrFail();

        return new JsonResource($repayments->handle($actor, $loan, $source, $quote['total_settlement_minor'], $request->validated('method'), $date, $request->idempotencyKey(), FoundationPermission::LoansSettle));
    }

    public function disburse(LoanFinancialRequest $request, string $loanUuid, TenantContext $context, DisburseApprovedLoan $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $source = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('source_wallet_uuid'))->firstOrFail();

        return new JsonResource($action->handle($actor, $this->find($context, $loanUuid), $source, $request->validated('destination_type'), $request->validated('method'), CarbonImmutable::parse($request->validated('date')), $request->idempotencyKey(), $request->validated('destination_reference')));
    }

    public function reverseDisbursement(LoanFinancialRequest $request, string $disbursementUuid, TenantContext $context, ReverseLoanDisbursement $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $disbursement = LoanDisbursement::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $disbursementUuid)->firstOrFail();

        return new JsonResource($action->handle($actor, $disbursement, $request->validated('reason'), $request->idempotencyKey()));
    }

    public function memberLoans(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoansView->value);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $memberUuid)->firstOrFail();

        return LoanResource::collection(Loan::query()->where('organisation_id', $context->organisation()->getKey())->where('member_id', $member->getKey())->with(['member', 'product'])->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function memberSummary(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::LoansView->value);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $memberUuid)->firstOrFail();
        $summary = Loan::query()->where('organisation_id', $context->organisation()->getKey())->where('member_id', $member->getKey())->where('status', '!=', LoanStatus::Reversed->value)->selectRaw('COUNT(*) loan_count, COALESCE(SUM(outstanding_principal_minor),0) outstanding_principal_minor, COALESCE(SUM(outstanding_interest_minor + outstanding_fees_minor + outstanding_penalties_minor),0) outstanding_charges_minor, COALESCE(SUM(arrears_minor),0) arrears_minor')->first();

        return ApiResponse::success($request, ['member_uuid' => $member->public_id, 'loan_count' => (int) $summary?->getAttribute('loan_count'), 'outstanding_principal_minor' => (int) $summary?->getAttribute('outstanding_principal_minor'), 'outstanding_charges_minor' => (int) $summary?->getAttribute('outstanding_charges_minor'), 'arrears_minor' => (int) $summary?->getAttribute('arrears_minor')]);
    }

    private function find(TenantContext $context, string $uuid): Loan
    {
        return Loan::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
