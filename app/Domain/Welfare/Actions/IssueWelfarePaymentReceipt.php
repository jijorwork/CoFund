<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\ReceiptNumberGenerator;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Models\User;
use Illuminate\Support\Str;

class IssueWelfarePaymentReceipt
{
    public function __construct(private ReceiptNumberGenerator $numbers) {}

    public function handle(WelfareBenefitPayment $payment, User $actor): Receipt
    {
        $code = Str::random(48);

        return Receipt::query()->create(['organisation_id' => $payment->organisation_id, 'branch_id' => $payment->branch_id, 'welfare_benefit_payment_id' => $payment->getKey(), 'financial_transaction_id' => $payment->financial_transaction_id, 'receipt_number' => $this->numbers->next($payment->claim->product->organisation, $payment->payment_date->year), 'receipt_type' => ReceiptType::WelfareBenefitPayment, 'status' => ReceiptStatus::Issued, 'issued_at' => now(), 'issued_by_user_id' => $actor->getKey(), 'member_id' => $payment->claim->member_id, 'payer_name' => $payment->recipient_name, 'payment_method' => $payment->payment_method, 'amount_minor' => $payment->amount_minor, 'currency_code' => $payment->currency_code, 'verification_code' => $code, 'verification_hash' => hash('sha256', $code), 'metadata' => ['claim_number' => $payment->claim->claim_number, 'payment_number' => $payment->payment_number]]);
    }
}
