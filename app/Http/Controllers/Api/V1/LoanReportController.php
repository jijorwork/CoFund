<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanDisbursement;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanReportController extends Controller
{
    public function portfolio(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);
        $query = Loan::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', LoanStatus::Reversed->value);
        $aggregate = (clone $query)->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) active_loans, COALESCE(SUM(disbursed_amount_minor),0) disbursed_principal, COALESCE(SUM(outstanding_principal_minor),0) outstanding_principal, COALESCE(SUM(outstanding_interest_minor),0) outstanding_interest, COALESCE(SUM(outstanding_principal_minor + outstanding_interest_minor + outstanding_fees_minor + outstanding_penalties_minor),0) total_outstanding, COALESCE(SUM(arrears_minor),0) arrears, COUNT(CASE WHEN performance_status = ? THEN 1 END) overdue_loans, COUNT(CASE WHEN performance_status = ? THEN 1 END) non_performing_loans, COUNT(CASE WHEN status = ? THEN 1 END) settled_loans', [LoanStatus::Active->value, LoanPerformanceStatus::Overdue->value, LoanPerformanceStatus::NonPerforming->value, LoanStatus::Settled->value])->first();
        $totalPrincipal = max(1, (int) $aggregate?->getAttribute('outstanding_principal'));
        $par30 = (int) (clone $query)->where('days_past_due', '>', 30)->sum('outstanding_principal_minor');
        $par90 = (int) (clone $query)->where('days_past_due', '>', 90)->sum('outstanding_principal_minor');

        return ApiResponse::success($request, ['active_loans' => (int) $aggregate?->getAttribute('active_loans'), 'disbursed_principal_minor' => (int) $aggregate?->getAttribute('disbursed_principal'), 'outstanding_principal_minor' => (int) $aggregate?->getAttribute('outstanding_principal'), 'outstanding_interest_minor' => (int) $aggregate?->getAttribute('outstanding_interest'), 'total_outstanding_minor' => (int) $aggregate?->getAttribute('total_outstanding'), 'arrears_minor' => (int) $aggregate?->getAttribute('arrears'), 'overdue_loans' => (int) $aggregate?->getAttribute('overdue_loans'), 'non_performing_loans' => (int) $aggregate?->getAttribute('non_performing_loans'), 'settled_loans' => (int) $aggregate?->getAttribute('settled_loans'), 'par_30_basis_points' => intdiv($par30 * 10000, $totalPrincipal), 'par_90_basis_points' => intdiv($par90 * 10000, $totalPrincipal)]);
    }

    public function disbursements(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);

        return JsonResource::collection(LoanDisbursement::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', 'reversed')->latest('disbursement_date')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function repayments(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);

        return JsonResource::collection(LoanRepayment::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', 'reversed')->latest('repayment_date')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function arrears(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);

        return JsonResource::collection(Loan::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('arrears_minor', '>', 0)->with('member')->orderByDesc('days_past_due')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function productPerformance(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);

        return JsonResource::collection(Loan::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', LoanStatus::Reversed->value)->selectRaw('loan_product_id, COUNT(*) loan_count, COALESCE(SUM(disbursed_amount_minor),0) disbursed_minor, COALESCE(SUM(outstanding_principal_minor),0) outstanding_principal_minor, COALESCE(SUM(arrears_minor),0) arrears_minor')->groupBy('loan_product_id')->with('product')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function guarantorExposure(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanReportsView->value);

        return JsonResource::collection(LoanGuarantor::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->whereHas('application', fn ($application) => $application->where('branch_id', $branch->getKey())))->where('status', GuarantorStatus::Accepted)->selectRaw('guarantor_member_id, COUNT(*) guarantee_count, COALESCE(SUM(guaranteed_amount_minor),0) exposure_minor')->groupBy('guarantor_member_id')->with('guarantor')->paginate(min($request->integer('per_page', 25), 100)));
    }
}
