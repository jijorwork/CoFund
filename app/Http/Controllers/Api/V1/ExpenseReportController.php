<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Expenses\Enums\ExpensePaymentStatus;
use App\Domain\Expenses\Models\ExpensePayment;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    public function expenses(Request $request): JsonResponse
    {
        $query = $this->expensesQuery();
        $totals = $query->toBase()->selectRaw('COUNT(*) request_count, COALESCE(SUM(requested_amount_minor),0) requested_total_minor, COALESCE(SUM(approved_amount_minor),0) approved_total_minor, COALESCE(SUM(paid_amount_minor),0) paid_total_minor, COALESCE(SUM(outstanding_amount_minor),0) outstanding_total_minor, COALESCE(AVG(requested_amount_minor),0) average_request_minor')->first();

        return $this->respond($request, (array) $totals);
    }

    public function payments(Request $request): JsonResponse
    {
        $totals = $this->paymentQuery()->toBase()->selectRaw('COUNT(*) payment_count, COALESCE(SUM(amount_minor),0) paid_total_minor')->first();

        return $this->respond($request, (array) $totals);
    }

    public function pettyCash(Request $request): JsonResponse
    {
        $totals = $this->advanceQuery()->toBase()->selectRaw('COUNT(*) advance_count, COALESCE(SUM(disbursed_amount_minor),0) disbursed_minor, COALESCE(SUM(retired_amount_minor),0) retired_minor, COALESCE(SUM(refundable_amount_minor),0) returned_cash_minor, COALESCE(SUM(shortage_minor),0) shortage_minor')->first();

        return $this->respond($request, (array) $totals);
    }

    public function categorySummary(Request $request): JsonResponse
    {
        $rows = $this->expensesQuery()->selectRaw('expense_category_id, COUNT(*) request_count, SUM(requested_amount_minor) requested_minor, SUM(paid_amount_minor) paid_minor')->groupBy('expense_category_id')->with('category:id,public_id,code,name')->get()->map(fn (ExpenseRequest $expense): array => ['category' => ['uuid' => $expense->category->public_id, 'code' => $expense->category->code, 'name' => $expense->category->name], 'request_count' => $expense->getAttribute('request_count'), 'requested_minor' => $expense->getAttribute('requested_minor'), 'paid_minor' => $expense->getAttribute('paid_minor')])->all();

        return $this->respond($request, ['categories' => $rows]);
    }

    public function outstandingAdvances(Request $request): JsonResponse
    {
        $rows = $this->advanceQuery()->whereRaw('disbursed_amount_minor > retired_amount_minor')->get(['public_id', 'advance_number', 'disbursed_amount_minor', 'retired_amount_minor', 'shortage_minor', 'currency_code', 'retirement_due_date'])->map(fn (PettyCashAdvance $advance): array => ['uuid' => $advance->public_id, 'advance_number' => $advance->advance_number, 'outstanding_minor' => $advance->disbursed_amount_minor - $advance->retired_amount_minor, 'shortage_minor' => $advance->shortage_minor, 'currency_code' => $advance->currency_code, 'retirement_due_date' => $advance->retirement_due_date?->toDateString()])->all();

        return $this->respond($request, ['advances' => $rows]);
    }

    /** @return Builder<ExpenseRequest> */
    private function expensesQuery(): Builder
    {
        return ExpenseRequest::query()->whereBelongsTo($this->context->organisation())->when($this->context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $this->context->branch()?->getKey()));
    }

    /** @return Builder<ExpensePayment> */
    private function paymentQuery(): Builder
    {
        return ExpensePayment::query()->whereBelongsTo($this->context->organisation())->where('status', ExpensePaymentStatus::Posted)->when($this->context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $this->context->branch()?->getKey()));
    }

    /** @return Builder<PettyCashAdvance> */
    private function advanceQuery(): Builder
    {
        return PettyCashAdvance::query()->whereBelongsTo($this->context->organisation())->when($this->context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $this->context->branch()?->getKey()));
    }

    /** @param array<string, mixed> $data */
    private function respond(Request $request, array $data): JsonResponse
    {
        $this->authorization->authorize(FoundationPermission::ExpenseReportsView->value);

        return ApiResponse::success($request, $data);
    }
}
