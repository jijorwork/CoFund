<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\ReceiptNumberGenerator;
use App\Models\User;
use Illuminate\Support\Str;

class IssueContributionReceipt
{
    public function __construct(private ReceiptNumberGenerator $numbers) {}

    public function handle(
        ContributionPayment $payment,
        User $actor,
        ReceiptType $type = ReceiptType::ContributionPayment,
        ?Receipt $original = null,
    ): Receipt {
        $verificationCode = Str::random(48);

        return Receipt::query()->create([
            'organisation_id' => $payment->organisation_id,
            'branch_id' => $payment->branch_id,
            'contribution_payment_id' => $payment->getKey(),
            'financial_transaction_id' => $payment->financial_transaction_id,
            'receipt_number' => $this->numbers->next(
                $payment->organisation,
                $payment->payment_date->year,
            ),
            'receipt_type' => $type,
            'status' => ReceiptStatus::Issued,
            'issued_at' => now(),
            'issued_by_user_id' => $actor->getKey(),
            'member_id' => $payment->member_id,
            'payer_name' => $payment->payer_name,
            'payment_method' => $payment->payment_method,
            'amount_minor' => $payment->amount_minor,
            'currency_code' => $payment->currency_code,
            'verification_code' => $verificationCode,
            'verification_hash' => hash('sha256', $verificationCode),
            'original_receipt_id' => $original?->getKey(),
            'metadata' => ['payment_number' => $payment->payment_number],
        ]);
    }
}
