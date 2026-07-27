<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalReportController extends Controller
{
    public function requests(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);
        $row = $this->requestsQuery($context)->selectRaw('COUNT(*) request_count, COALESCE(SUM(requested_amount_minor),0) requested_minor, COALESCE(SUM(eligible_amount_minor),0) eligible_minor, COALESCE(SUM(approved_amount_minor),0) approved_minor, COALESCE(SUM(total_paid_minor),0) paid_minor, COALESCE(SUM(outstanding_approved_minor),0) outstanding_minor')->first();

        return ApiResponse::success($request, ['request_count' => (int) $row?->getAttribute('request_count'), 'requested_minor' => (int) $row?->getAttribute('requested_minor'), 'eligible_minor' => (int) $row?->getAttribute('eligible_minor'), 'approved_minor' => (int) $row?->getAttribute('approved_minor'), 'gross_paid_minor' => (int) $row?->getAttribute('paid_minor'), 'outstanding_minor' => (int) $row?->getAttribute('outstanding_minor')]);
    }

    public function payouts(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection(WithdrawalPayout::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', 'posted')->latest('payout_date')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function outstanding(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection($this->requestsQuery($context)->where('outstanding_approved_minor', '>', 0)->with(['member', 'product'])->orderByDesc('outstanding_approved_minor')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function holds(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection(SavingsHold::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->selectRaw('hold_type, status, COUNT(*) hold_count, COALESCE(SUM(amount_minor),0) amount_minor')->groupBy(['hold_type', 'status'])->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function fees(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);
        $row = WithdrawalPayout::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', 'posted')->selectRaw('COUNT(*) payout_count, COALESCE(SUM(gross_withdrawal_minor),0) gross_minor, COALESCE(SUM(fee_minor),0) fee_minor, COALESCE(SUM(net_payout_minor),0) net_minor')->first();

        return ApiResponse::success($request, ['payout_count' => (int) $row?->getAttribute('payout_count'), 'gross_minor' => (int) $row?->getAttribute('gross_minor'), 'fee_minor' => (int) $row?->getAttribute('fee_minor'), 'net_minor' => (int) $row?->getAttribute('net_minor')]);
    }

    public function rejections(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection($this->requestsQuery($context)->where('status', 'rejected')->select(['public_id', 'request_number', 'rejection_reason', 'rejected_at'])->latest('rejected_at')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function productPerformance(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection($this->requestsQuery($context)->selectRaw('withdrawal_product_id, COUNT(*) request_count, COALESCE(SUM(approved_amount_minor),0) approved_minor, COALESCE(SUM(total_paid_minor),0) gross_paid_minor, COALESCE(SUM(outstanding_approved_minor),0) outstanding_minor')->groupBy('withdrawal_product_id')->with('product')->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function restrictions(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReportsView->value);

        return JsonResource::collection($this->requestsQuery($context)->whereNotNull('restriction_snapshot')->select(['public_id', 'request_number', 'eligibility_status', 'restriction_snapshot', 'request_date'])->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function memberRequests(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $member = $this->member($context, $memberUuid);

        return JsonResource::collection($this->requestsQuery($context)->where('member_id', $member->getKey())->with(['product', 'reason'])->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function memberSummary(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $member = $this->member($context, $memberUuid);
        $requests = $this->requestsQuery($context)->where('member_id', $member->getKey());
        $row = (clone $requests)->selectRaw('COUNT(*) submitted_count, SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) approved_count, SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) paid_count, COALESCE(SUM(total_paid_minor),0) gross_paid_minor, COALESCE(SUM(outstanding_approved_minor),0) pending_approved_minor', ['approved', 'paid'])->first();
        $payouts = WithdrawalPayout::query()->where('organisation_id', $context->organisation()->getKey())->whereHas('request', fn (Builder $query) => $query->where('member_id', $member->getKey()))->where('status', 'posted')->selectRaw('COALESCE(SUM(fee_minor),0) fees_minor, COALESCE(SUM(net_payout_minor),0) net_minor')->first();

        return ApiResponse::success($request, ['member_uuid' => $member->public_id, 'submitted_count' => (int) $row?->getAttribute('submitted_count'), 'approved_count' => (int) $row?->getAttribute('approved_count'), 'paid_count' => (int) $row?->getAttribute('paid_count'), 'gross_withdrawals_paid_minor' => (int) $row?->getAttribute('gross_paid_minor'), 'fees_charged_minor' => (int) $payouts?->getAttribute('fees_minor'), 'net_payouts_minor' => (int) $payouts?->getAttribute('net_minor'), 'pending_approved_minor' => (int) $row?->getAttribute('pending_approved_minor'), 'latest_request' => (clone $requests)->latest()->first(['public_id', 'request_number', 'status', 'request_date'])]);
    }

    /** @return Builder<WithdrawalRequest> */
    private function requestsQuery(TenantContext $context): Builder
    {
        return WithdrawalRequest::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('status', '!=', 'reversed');
    }

    private function member(TenantContext $context, string $uuid): Member
    {
        return Member::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
