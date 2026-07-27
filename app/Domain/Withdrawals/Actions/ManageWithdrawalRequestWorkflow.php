<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Enums\SavingsHoldStatus;
use App\Domain\Withdrawals\Enums\WithdrawalRequestStatus;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\CalculateWithdrawableSavings;
use App\Domain\Withdrawals\Services\CalculateWithdrawalFee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageWithdrawalRequestWorkflow
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateWithdrawableSavings $withdrawable, private CalculateWithdrawalFee $fees, private ManageSavingsHold $holds) {}

    public function submit(User $actor, WithdrawalRequest $request): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsSubmit->value);

        return DB::transaction(function () use ($actor, $request): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->with(['member', 'product', 'reason', 'savingsWallet'])->lockForUpdate()->where('organisation_id', $this->context->organisation()->getKey())->whereKey($request->getKey())->firstOrFail();
            if ($locked->status !== WithdrawalRequestStatus::Draft) {
                throw new InvalidWithdrawalOperation('Only draft withdrawal requests may be submitted.');
            }
            $calculation = $this->withdrawable->calculate($locked->member, $locked->product, $locked->savingsWallet, $locked->reason, $locked);
            if ($locked->requested_amount_minor > $calculation['withdrawable_balance_minor'] || $calculation['blocking_reasons'] !== []) {
                throw new InvalidWithdrawalOperation('Current withdrawal restrictions prevent submission.');
            }
            $fee = $this->fees->calculate($locked->product, $locked->requested_amount_minor);
            $hold = $this->holds->create($actor, $locked->member, $locked->savingsWallet, 'pending_withdrawal', $locked->requested_amount_minor, 'Pending withdrawal '.$locked->request_number, 'withdrawal_request', $locked->public_id);
            $locked->terms()->create(['organisation_id' => $locked->organisation_id, 'currency_code' => $locked->currency_code, 'requested_amount_minor' => $locked->requested_amount_minor, 'eligible_amount_minor' => $calculation['withdrawable_balance_minor'], 'fee_type' => $fee['fee_type'], 'fee_value' => $locked->product->fee_value, 'fee_minor' => $fee['fee_minor'], 'net_payout_minor' => $fee['net_payout_minor'], 'minimum_remaining_balance_minor' => $calculation['minimum_required_balance_minor'], 'active_holds_minor' => $calculation['active_holds_minor'], 'loan_restriction_minor' => $calculation['loan_security_minor'], 'guarantor_restriction_minor' => $calculation['guarantor_security_minor'], 'welfare_restriction_minor' => $calculation['welfare_restriction_minor'], 'pending_withdrawal_reservations_minor' => $calculation['pending_withdrawal_reservations_minor'], 'eligibility_snapshot' => $calculation, 'restriction_snapshot' => $calculation['calculation_breakdown'], 'calculation_snapshot' => $fee]);
            $locked->update(['status' => WithdrawalRequestStatus::UnderReview, 'eligibility_status' => 'eligible', 'submitted_at' => now(), 'submitted_by_user_id' => $actor->getKey(), 'reservation_hold_id' => $hold->getKey(), 'fee_minor' => $fee['fee_minor'], 'net_payout_minor' => $fee['net_payout_minor']]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function review(User $actor, WithdrawalRequest $request, ?string $reason = null): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsReview->value);
        if ($request->status !== WithdrawalRequestStatus::UnderReview || $request->created_by_user_id === $actor->getKey()) {
            throw new InvalidWithdrawalOperation('The request cannot be reviewed by this actor.');
        }
        $request->approvals()->create(['organisation_id' => $request->organisation_id, 'approval_level' => 'review', 'decision' => 'reviewed', 'requested_at' => $request->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actor->getKey(), 'acting_role_id' => $this->context->role()->getKey(), 'reason' => $reason, 'sequence_number' => $request->approvals()->count() + 1]);
        $request->update(['status' => WithdrawalRequestStatus::PendingApproval, 'review_status' => 'reviewed', 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->getKey()]);

        return $request->refresh();
    }

    public function approve(User $actor, WithdrawalRequest $request, int $amountMinor, bool $secondary = false, ?string $reason = null): WithdrawalRequest
    {
        $this->authorization->authorize(($secondary ? FoundationPermission::WithdrawalRequestsSecondaryApprove : FoundationPermission::WithdrawalRequestsApprove)->value);

        return DB::transaction(function () use ($actor, $request, $amountMinor, $secondary, $reason): WithdrawalRequest {
            $locked = WithdrawalRequest::query()->with(['member', 'product', 'reason', 'savingsWallet', 'approvals', 'reservationHold'])->lockForUpdate()->whereKey($request->getKey())->firstOrFail();
            $actorId = $actor->getKey();
            if (in_array($actorId, array_filter([$locked->created_by_user_id, $locked->reviewed_by_user_id]), true) || $locked->approvals->contains('decided_by_user_id', $actorId)) {
                throw new InvalidWithdrawalOperation('Separation of duties requires a different approver.');
            }
            $calculation = $this->withdrawable->calculate($locked->member, $locked->product, $locked->savingsWallet, $locked->reason, $locked);
            if ($amountMinor <= 0 || $amountMinor > $locked->requested_amount_minor || $amountMinor > $calculation['withdrawable_balance_minor']) {
                throw new InvalidWithdrawalOperation('Approved amount exceeds the current withdrawal limit.');
            }
            $secondaryRequired = $locked->is_full_balance || $locked->product->require_secondary_approval || ($locked->product->secondary_approval_threshold_minor !== null && $amountMinor >= $locked->product->secondary_approval_threshold_minor);
            if ($secondary && $locked->approval_status !== 'primary_approved') {
                throw new InvalidWithdrawalOperation('Primary approval is required first.');
            }
            $final = $secondary || ! $secondaryRequired;
            $fee = $this->fees->calculate($locked->product, $amountMinor);
            $locked->approvals()->create(['organisation_id' => $locked->organisation_id, 'approval_level' => $secondary ? 'secondary' : 'primary', 'decision' => 'approved', 'requested_at' => $locked->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actorId, 'acting_role_id' => $this->context->role()->getKey(), 'recommended_amount_minor' => $locked->eligible_amount_minor, 'approved_amount_minor' => $amountMinor, 'reason' => $reason, 'sequence_number' => $locked->approvals->count() + 1]);
            $locked->reservationHold?->update(['amount_minor' => $amountMinor]);
            if ($final) {
                $locked->terms?->update(['approved_amount_minor' => $amountMinor, 'fee_minor' => $fee['fee_minor'], 'net_payout_minor' => $fee['net_payout_minor']]);
            }
            $locked->update(['status' => $final ? WithdrawalRequestStatus::Approved : WithdrawalRequestStatus::PendingSecondaryApproval, 'approval_status' => $final ? 'approved' : 'primary_approved', 'payment_status' => $final ? 'unpaid' : 'not_approved', 'approved_amount_minor' => $amountMinor, 'fee_minor' => $fee['fee_minor'], 'net_payout_minor' => $fee['net_payout_minor'], 'outstanding_approved_minor' => $final ? $amountMinor : 0, 'approved_at' => $final ? now() : null, 'approved_by_user_id' => $final ? $actorId : null]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, WithdrawalRequest $request, string $reason): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsReject->value);
        if (trim($reason) === '' || $request->payouts()->where('status', 'posted')->exists()) {
            throw new InvalidWithdrawalOperation('A reason is required and active payouts prevent rejection.');
        }
        $request->reservationHold?->update(['status' => SavingsHoldStatus::Released, 'released_at' => now(), 'released_by_user_id' => $actor->getKey(), 'release_reason' => 'Withdrawal rejected']);
        $request->update(['status' => WithdrawalRequestStatus::Rejected, 'approval_status' => 'rejected', 'rejected_at' => now(), 'rejected_by_user_id' => $actor->getKey(), 'rejection_reason' => $reason]);

        return $request->refresh();
    }

    public function cancel(User $actor, WithdrawalRequest $request, string $reason): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsCancel->value);
        if (trim($reason) === '' || $request->payouts()->where('status', 'posted')->exists()) {
            throw new InvalidWithdrawalOperation('A reason is required and active payouts prevent cancellation.');
        }
        $request->reservationHold?->update(['status' => SavingsHoldStatus::Released, 'released_at' => now(), 'released_by_user_id' => $actor->getKey(), 'release_reason' => 'Withdrawal cancelled']);
        $request->update(['status' => WithdrawalRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason]);

        return $request->refresh();
    }

    public function settle(WithdrawalRequest $request): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsSettle->value);
        $activePaid = (int) $request->payouts()->where('status', 'posted')->sum('gross_withdrawal_minor');
        if ($request->approved_amount_minor === null || $activePaid !== $request->approved_amount_minor || $request->reservationHold?->status === SavingsHoldStatus::Active) {
            throw new InvalidWithdrawalOperation('The withdrawal is not fully paid and releasable.');
        }
        $request->update(['status' => WithdrawalRequestStatus::Settled, 'payment_status' => 'paid', 'outstanding_approved_minor' => 0, 'settled_at' => now()]);

        return $request->refresh();
    }
}
