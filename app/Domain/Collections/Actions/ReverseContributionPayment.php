<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Enums\PaymentAllocationStatus;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Services\ApplyPaymentAllocationToObligation;
use App\Domain\Collections\Services\PaymentNumberGenerator;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReverseContributionPayment
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private ReverseFinancialTransaction $reverseTransaction,
        private ApplyPaymentAllocationToObligation $satisfaction,
        private PaymentNumberGenerator $numbers,
        private IssueContributionReceipt $receipts,
    ) {}

    public function handle(
        ContributionPayment $original,
        User $actor,
        string $reason,
        string $idempotencyKey,
    ): ContributionPayment {
        $this->authorization->authorize(FoundationPermission::ContributionPaymentsReverse->value);

        if (trim($reason) === '' || trim($idempotencyKey) === '') {
            throw new InvalidContributionPayment('A reversal reason and idempotency key are required.');
        }

        return DB::transaction(function () use ($original, $actor, $reason, $idempotencyKey): ContributionPayment {
            $fingerprint = hash('sha256', $original->public_id.'|'.trim($reason));
            $existing = ContributionPayment::query()
                ->whereBelongsTo($this->context->organisation())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidContributionPayment('The idempotency key was reused with a different payload.');
                }

                return $existing->load(['receipt', 'financialTransaction', 'reversedPayment']);
            }

            $locked = ContributionPayment::query()
                ->with(['organisation', 'financialTransaction', 'receipt', 'allocations.obligation'])
                ->whereBelongsTo($this->context->organisation())
                ->whereKey($original->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ContributionPaymentStatus::Reversed || $locked->reversal_payment_id !== null) {
                throw new InvalidContributionPayment('The contribution payment has already been reversed.');
            }

            if (
                $this->context->roleAssignment()->branch_id !== null
                && $locked->branch_id !== $this->context->branch()?->getKey()
            ) {
                throw new InvalidContributionPayment;
            }

            $transaction = $this->reverseTransaction->handle(
                $locked->financialTransaction,
                $actor,
                $reason,
                'contribution-payment-reversal:'.$idempotencyKey,
                FoundationPermission::ContributionPaymentsReverse->value,
            );
            $reversal = ContributionPayment::query()->create([
                'public_id' => (string) Str::uuid7(),
                'organisation_id' => $locked->organisation_id,
                'branch_id' => $locked->branch_id,
                'member_id' => $locked->member_id,
                'contribution_plan_id' => $locked->contribution_plan_id,
                'member_plan_enrolment_id' => $locked->member_plan_enrolment_id,
                'financial_transaction_id' => $transaction->getKey(),
                'payment_number' => $this->numbers->next($locked->organisation, now()->year),
                'payment_method' => $locked->payment_method,
                'source_wallet_id' => $locked->source_wallet_id,
                'destination_wallet_id' => $locked->destination_wallet_id,
                'amount_minor' => $locked->amount_minor,
                'allocated_amount_minor' => $locked->allocated_amount_minor,
                'unallocated_amount_minor' => $locked->unallocated_amount_minor,
                'currency_code' => $locked->currency_code,
                'payment_date' => now($locked->organisation->timezone)->toDateString(),
                'received_at' => now(),
                'status' => ContributionPaymentStatus::Posted,
                'external_reference' => $locked->external_reference,
                'payer_name' => $locked->payer_name,
                'payer_phone' => $locked->payer_phone,
                'collected_by_user_id' => $actor->getKey(),
                'collector_wallet_id' => $locked->collector_wallet_id,
                'collector_collection_session_id' => $locked->collector_collection_session_id,
                'notes' => 'Reversal of '.$locked->payment_number,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'reversed_payment_id' => $locked->getKey(),
                'reversal_reason' => $reason,
            ]);

            foreach ($locked->allocations->where('status', PaymentAllocationStatus::Active) as $index => $allocation) {
                $opposite = ContributionPaymentAllocation::query()->create([
                    'organisation_id' => $locked->organisation_id,
                    'contribution_payment_id' => $reversal->getKey(),
                    'contribution_obligation_id' => $allocation->contribution_obligation_id,
                    'member_plan_enrolment_id' => $allocation->member_plan_enrolment_id,
                    'member_id' => $allocation->member_id,
                    'amount_minor' => $allocation->amount_minor,
                    'currency_code' => $allocation->currency_code,
                    'allocation_order' => $index + 1,
                    'status' => PaymentAllocationStatus::Reversed,
                    'reversed_allocation_id' => $allocation->getKey(),
                    'allocated_at' => now(),
                    'created_by_user_id' => $actor->getKey(),
                ]);
                $allocation->update([
                    'status' => PaymentAllocationStatus::Reversed,
                    'reversal_allocation_id' => $opposite->getKey(),
                ]);
                $this->satisfaction->recalculate($allocation->obligation);
            }

            $receipt = $this->receipts->handle(
                $reversal->load('organisation'),
                $actor,
                ReceiptType::ContributionReversal,
                $locked->receipt,
            );
            $reversal->update(['receipt_id' => $receipt->getKey()]);
            $locked->update([
                'status' => ContributionPaymentStatus::Reversed,
                'reversal_payment_id' => $reversal->getKey(),
                'reversal_reason' => $reason,
            ]);
            $locked->receipt->update([
                'status' => ReceiptStatus::Reversed,
                'reversal_receipt_id' => $receipt->getKey(),
            ]);

            if ($locked->collector_collection_session_id !== null) {
                $locked->session()->whereIn('status', [
                    CollectorSessionStatus::Submitted,
                    CollectorSessionStatus::UnderReview,
                    CollectorSessionStatus::Approved,
                ])->update([
                    'status' => CollectorSessionStatus::UnderReview,
                    'approved_at' => null,
                    'approved_by_user_id' => null,
                ]);
            }

            return $reversal->refresh()->load(['receipt', 'financialTransaction', 'reversedPayment']);
        }, attempts: 3);
    }
}
