<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\ReceiptNumberGenerator;
use App\Domain\Loans\Models\LoanRepayment;
use App\Models\User;
use Illuminate\Support\Str;

class IssueLoanRepaymentReceipt
{
    public function __construct(private ReceiptNumberGenerator $numbers) {}

    public function handle(LoanRepayment $repayment, User $actor): Receipt
    {
        $verificationCode = Str::random(48);

        return Receipt::query()->create([
            'organisation_id' => $repayment->organisation_id,
            'branch_id' => $repayment->branch_id,
            'loan_repayment_id' => $repayment->getKey(),
            'financial_transaction_id' => $repayment->financial_transaction_id,
            'receipt_number' => $this->numbers->next($repayment->loan->application->organisation, $repayment->repayment_date->year),
            'receipt_type' => ReceiptType::LoanRepayment,
            'status' => ReceiptStatus::Issued,
            'issued_at' => now(),
            'issued_by_user_id' => $actor->getKey(),
            'member_id' => $repayment->member_id,
            'payer_name' => $repayment->loan->member->fullName(),
            'payment_method' => $repayment->payment_method,
            'amount_minor' => $repayment->amount_minor,
            'currency_code' => $repayment->currency_code,
            'verification_code' => $verificationCode,
            'verification_hash' => hash('sha256', $verificationCode),
            'metadata' => [
                'loan_number' => $repayment->loan->loan_number,
                'repayment_number' => $repayment->repayment_number,
            ],
        ]);
    }
}
