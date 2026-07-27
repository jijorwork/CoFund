<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Actions\CreateWithdrawalRequest;
use App\Domain\Withdrawals\Actions\ManageWithdrawalRequestWorkflow;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\CalculateWithdrawableSavings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WithdrawalRequestForm;
use App\Http\Resources\Api\V1\WithdrawalRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalRequestController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $query = WithdrawalRequest::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'product', 'reason', 'savingsWallet'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()));
        foreach (['request_number', 'status', 'eligibility_status', 'approval_status', 'payment_status'] as $field) {
            $query->when($request->input('filter.'.$field), fn ($query, $value) => $query->where($field, $value));
        }

        return WithdrawalRequestResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(WithdrawalRequestForm $request, TenantContext $context, CreateWithdrawalRequest $action): WithdrawalRequestResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = Member::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('member_uuid'))->firstOrFail();
        $product = WithdrawalProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('product_uuid'))->firstOrFail();
        $wallet = Wallet::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('savings_wallet_uuid'))->firstOrFail();
        $reasonUuid = $request->validated('reason_uuid');
        $reason = $reasonUuid === null ? null : WithdrawalReason::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $reasonUuid)->firstOrFail();

        return WithdrawalRequestResource::make($action->handle($actor, $member, $product, $wallet, $reason, $request->integer('requested_amount_minor'), $request->validated('reason_text'), $request->boolean('is_full_balance'))->load(['member', 'product', 'reason', 'savingsWallet']));
    }

    public function show(string $requestUuid, TenantContext $context, RoleAuthorization $authorization): WithdrawalRequestResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);

        return WithdrawalRequestResource::make($this->find($context, $requestUuid)->load(['member', 'product', 'reason', 'savingsWallet', 'terms', 'approvals', 'payouts']));
    }

    public function eligibility(string $requestUuid, TenantContext $context, RoleAuthorization $authorization, CalculateWithdrawableSavings $service): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $withdrawal = $this->find($context, $requestUuid)->load(['member', 'product', 'reason', 'savingsWallet']);

        return new JsonResource($service->calculate($withdrawal->member, $withdrawal->product, $withdrawal->savingsWallet, $withdrawal->reason, $withdrawal));
    }

    public function position(string $requestUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $withdrawal = $this->find($context, $requestUuid)->load('reservationHold');
        $payouts = $withdrawal->payouts()->where('status', 'posted');

        return new JsonResource(['requested_amount_minor' => $withdrawal->requested_amount_minor, 'eligible_amount_minor' => $withdrawal->eligible_amount_minor, 'approved_amount_minor' => $withdrawal->approved_amount_minor, 'gross_paid_minor' => (int) (clone $payouts)->sum('gross_withdrawal_minor'), 'fee_charged_minor' => (int) (clone $payouts)->sum('fee_minor'), 'net_paid_minor' => (int) (clone $payouts)->sum('net_payout_minor'), 'outstanding_approved_minor' => max(0, (int) $withdrawal->approved_amount_minor - (int) (clone $payouts)->sum('gross_withdrawal_minor')), 'active_reservation_minor' => $withdrawal->reservationHold?->status->value === 'active' ? $withdrawal->reservationHold->amount_minor : 0, 'payment_status' => $withdrawal->payment_status, 'request_status' => $withdrawal->status->value]);
    }

    public function workflow(WithdrawalRequestForm $request, string $requestUuid, string $transition, TenantContext $context, ManageWithdrawalRequestWorkflow $action): WithdrawalRequestResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $withdrawal = $this->find($context, $requestUuid);
        $result = match ($transition) {
            'submit' => $action->submit($actor, $withdrawal),
            'review' => $action->review($actor, $withdrawal, $request->validated('reason')),
            'approve' => $action->approve($actor, $withdrawal, $request->integer('approved_amount_minor'), false, $request->validated('reason')),
            'secondary-approve' => $action->approve($actor, $withdrawal, $request->integer('approved_amount_minor'), true, $request->validated('reason')),
            'reject' => $action->reject($actor, $withdrawal, (string) $request->validated('reason')),
            'cancel' => $action->cancel($actor, $withdrawal, (string) $request->validated('reason')),
            'settle' => $action->settle($withdrawal),
            default => throw new \InvalidArgumentException('Unsupported withdrawal transition.'),
        };

        return WithdrawalRequestResource::make($result->load(['member', 'product', 'reason', 'savingsWallet']));
    }

    private function find(TenantContext $context, string $uuid): WithdrawalRequest
    {
        return WithdrawalRequest::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
