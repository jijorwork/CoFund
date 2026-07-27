<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Models\Receipt;

class BuildContributionReceiptView
{
    /** @return array<string, mixed> */
    public function build(Receipt $receipt): array
    {
        if ($receipt->welfare_benefit_payment_id !== null) {
            return $this->buildWelfareBenefitPayment($receipt);
        }
        if ($receipt->loan_repayment_id !== null) {
            return $this->buildLoanRepayment($receipt);
        }

        $receipt->loadMissing([
            'organisation', 'branch', 'member', 'issuedBy',
            'payment.contributionPlan', 'payment.enrolment', 'payment.allocations.obligation',
            'financialTransaction',
        ]);
        $payment = $receipt->payment;

        return [
            'organisation' => ['name' => $receipt->organisation->name],
            'branch' => $receipt->branch === null ? null : [
                'name' => $receipt->branch->name,
                'code' => $receipt->branch->code,
            ],
            'receipt_number' => $receipt->receipt_number,
            'transaction_number' => $receipt->financialTransaction->transaction_number,
            'member' => [
                'name' => $receipt->member->fullName(),
                'membership_number' => $receipt->member->membership_number,
            ],
            'plan' => $payment->contributionPlan === null ? null : [
                'name' => $payment->contributionPlan->name,
                'code' => $payment->contributionPlan->code,
            ],
            'payment_date' => $payment->payment_date->toDateString(),
            'received_at' => $payment->received_at->toIso8601String(),
            'payment_method' => $receipt->payment_method,
            'amount_minor' => $receipt->amount_minor,
            'amount_formatted' => $this->formatMoney($receipt->amount_minor, $receipt->currency_code),
            'allocated_amount_minor' => $payment->allocated_amount_minor,
            'unallocated_amount_minor' => $payment->unallocated_amount_minor,
            'allocations' => $payment->allocations->map(fn (ContributionPaymentAllocation $allocation): array => [
                'obligation_uuid' => $allocation->obligation->public_id,
                'due_date' => $allocation->obligation->due_date->toDateString(),
                'amount_minor' => $allocation->amount_minor,
                'status' => $allocation->status->value,
            ])->values()->all(),
            'received_by' => $receipt->issuedBy->name,
            'verification_code' => $receipt->verification_code,
            'status' => $receipt->status->value,
        ];
    }

    /** @return array<string, mixed> */
    private function buildWelfareBenefitPayment(Receipt $receipt): array
    {
        $receipt->loadMissing(['organisation', 'branch', 'member', 'issuedBy', 'welfareBenefitPayment.claim', 'financialTransaction']);
        $payment = $receipt->welfareBenefitPayment;

        return [
            'organisation' => ['name' => $receipt->organisation->name],
            'branch' => $receipt->branch === null ? null : ['name' => $receipt->branch->name, 'code' => $receipt->branch->code],
            'receipt_number' => $receipt->receipt_number,
            'transaction_number' => $receipt->financialTransaction->transaction_number,
            'member' => ['name' => $receipt->member->fullName(), 'membership_number' => $receipt->member->membership_number],
            'welfare_claim' => ['uuid' => $payment->claim->public_id, 'claim_number' => $payment->claim->claim_number],
            'welfare_payment' => ['uuid' => $payment->public_id, 'payment_number' => $payment->payment_number, 'date' => $payment->payment_date->toDateString(), 'recipient_name' => $payment->recipient_name],
            'payment_method' => $receipt->payment_method,
            'amount_minor' => $receipt->amount_minor,
            'amount_formatted' => $this->formatMoney($receipt->amount_minor, $receipt->currency_code),
            'received_by' => $receipt->issuedBy->name,
            'verification_code' => $receipt->verification_code,
            'status' => $receipt->status->value,
        ];
    }

    /** @return array<string, mixed> */
    private function buildLoanRepayment(Receipt $receipt): array
    {
        $receipt->loadMissing([
            'organisation', 'branch', 'member', 'issuedBy',
            'loanRepayment.loan', 'loanRepayment.allocations',
            'financialTransaction',
        ]);
        $repayment = $receipt->loanRepayment;

        return [
            'organisation' => ['name' => $receipt->organisation->name],
            'branch' => $receipt->branch === null ? null : ['name' => $receipt->branch->name, 'code' => $receipt->branch->code],
            'receipt_number' => $receipt->receipt_number,
            'transaction_number' => $receipt->financialTransaction->transaction_number,
            'member' => ['name' => $receipt->member->fullName(), 'membership_number' => $receipt->member->membership_number],
            'loan' => ['uuid' => $repayment->loan->public_id, 'loan_number' => $repayment->loan->loan_number],
            'repayment' => [
                'uuid' => $repayment->public_id,
                'repayment_number' => $repayment->repayment_number,
                'date' => $repayment->repayment_date->toDateString(),
                'principal_minor' => $repayment->principal_allocated_minor,
                'interest_minor' => $repayment->interest_allocated_minor,
                'fees_minor' => $repayment->fee_allocated_minor,
                'penalties_minor' => $repayment->penalty_allocated_minor,
            ],
            'payment_method' => $receipt->payment_method,
            'amount_minor' => $receipt->amount_minor,
            'amount_formatted' => $this->formatMoney($receipt->amount_minor, $receipt->currency_code),
            'received_by' => $receipt->issuedBy->name,
            'verification_code' => $receipt->verification_code,
            'status' => $receipt->status->value,
        ];
    }

    private function formatMoney(int $amountMinor, string $currency): string
    {
        $symbol = strtoupper($currency) === 'GHS' ? 'GH₵' : strtoupper($currency).' ';

        return $symbol.number_format($amountMinor / 100, 2, '.', ',');
    }
}
