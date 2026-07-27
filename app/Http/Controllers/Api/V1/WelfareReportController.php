<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfareReportController extends Controller
{
    public function memberEnrolments(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareEnrolmentsView->value);
        $member = $this->member($context, $memberUuid);

        return JsonResource::collection(WelfareEnrolment::query()->where('organisation_id', $context->organisation()->getKey())->where('member_id', $member->getKey())->with('product')->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function memberClaims(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);
        $member = $this->member($context, $memberUuid);

        return JsonResource::collection($this->claimsQuery($context)->where('member_id', $member->getKey())->with(['product', 'event'])->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function memberSummary(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);
        $member = $this->member($context, $memberUuid);
        $claims = $this->claimsQuery($context)->where('member_id', $member->getKey());
        $totals = (clone $claims)->selectRaw('COUNT(*) claims_submitted, SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) claims_approved, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) claims_rejected, COALESCE(SUM(approved_benefit_minor),0) benefits_approved_minor, COALESCE(SUM(total_paid_minor),0) benefits_paid_minor, COALESCE(SUM(outstanding_payable_minor),0) outstanding_benefits_minor', ['approved', 'rejected'])->first();
        $enrolments = WelfareEnrolment::query()->where('organisation_id', $context->organisation()->getKey())->where('member_id', $member->getKey())->where('status', 'active')->with('product')->get();

        return ApiResponse::success($request, ['member_uuid' => $member->public_id, 'active_enrolments' => $enrolments->count(), 'coverage' => $enrolments->map(fn ($enrolment): array => ['product_uuid' => $enrolment->product->public_id, 'product_name' => $enrolment->product->name, 'coverage_start_date' => $enrolment->coverage_start_date->toDateString(), 'waiting_period_end_date' => $enrolment->waiting_period_end_date?->toDateString(), 'waiting_period_complete' => $enrolment->waiting_period_end_date === null || $enrolment->waiting_period_end_date->isPast()])->values(), 'claims_submitted' => (int) $totals?->getAttribute('claims_submitted'), 'claims_approved' => (int) $totals?->getAttribute('claims_approved'), 'claims_rejected' => (int) $totals?->getAttribute('claims_rejected'), 'benefits_approved_minor' => (int) $totals?->getAttribute('benefits_approved_minor'), 'benefits_paid_minor' => (int) $totals?->getAttribute('benefits_paid_minor'), 'outstanding_benefits_minor' => (int) $totals?->getAttribute('outstanding_benefits_minor'), 'latest_claim' => (clone $claims)->latest()->first(['public_id', 'claim_number', 'status', 'claim_date'])]);
    }

    public function claims(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);
        $query = $this->claimsQuery($context);
        $row = $query->selectRaw('COUNT(*) claim_count, COALESCE(SUM(requested_benefit_minor),0) requested_minor, COALESCE(SUM(approved_benefit_minor),0) approved_minor, COALESCE(SUM(total_paid_minor),0) paid_minor, COALESCE(SUM(outstanding_payable_minor),0) outstanding_minor, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) settled_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) partial_count', ['settled', 'partially_paid'])->first();

        return ApiResponse::success($request, ['claim_count' => (int) $row?->getAttribute('claim_count'), 'requested_benefits_minor' => (int) $row?->getAttribute('requested_minor'), 'approved_benefits_minor' => (int) $row?->getAttribute('approved_minor'), 'benefits_paid_minor' => (int) $row?->getAttribute('paid_minor'), 'outstanding_payables_minor' => (int) $row?->getAttribute('outstanding_minor'), 'settled_claims' => (int) $row?->getAttribute('settled_count'), 'partial_payment_claims' => (int) $row?->getAttribute('partial_count')]);
    }

    public function benefits(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);

        return JsonResource::collection(WelfareBenefitPayment::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', 'posted')->latest('payment_date')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function outstanding(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);

        return JsonResource::collection($this->claimsQuery($context)->where('outstanding_payable_minor', '>', 0)->with(['member', 'product'])->orderByDesc('outstanding_payable_minor')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function productPerformance(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);

        return JsonResource::collection($this->claimsQuery($context)->selectRaw('welfare_product_id, COUNT(*) claim_count, COALESCE(SUM(approved_benefit_minor),0) approved_minor, COALESCE(SUM(total_paid_minor),0) paid_minor, COALESCE(SUM(outstanding_payable_minor),0) outstanding_minor')->groupBy('welfare_product_id')->with('product')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function beneficiaryPayments(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);

        return JsonResource::collection(WelfareClaimBeneficiary::query()->where('organisation_id', $context->organisation()->getKey())->withSum(['claim.payments as paid_minor' => fn ($query) => $query->where('status', 'posted')], 'amount_minor')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function eligibilityRejections(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareReportsView->value);

        return JsonResource::collection($this->claimsQuery($context)->where('eligibility_status', 'ineligible')->select(['public_id', 'claim_number', 'eligibility_snapshot', 'rejection_reason', 'claim_date'])->paginate(min($request->integer('per_page', 25), 100)));
    }

    /** @return Builder<WelfareClaim> */
    private function claimsQuery(TenantContext $context): Builder
    {
        return WelfareClaim::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', 'reversed');
    }

    private function member(TenantContext $context, string $uuid): Member
    {
        return Member::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
