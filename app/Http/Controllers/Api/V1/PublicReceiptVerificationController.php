<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Models\Receipt;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReceiptVerificationController extends Controller
{
    public function __invoke(Request $request, string $verificationCode): JsonResponse
    {
        $hash = hash('sha256', $verificationCode);
        $receipt = Receipt::query()
            ->with(['organisation', 'member', 'payment', 'loanRepayment', 'welfareBenefitPayment', 'financialTransaction'])
            ->where('verification_hash', $hash)
            ->first();

        if ($receipt === null || ! hash_equals($receipt->verification_hash, $hash)) {
            return ApiResponse::success($request, ['valid' => false]);
        }

        return ApiResponse::success($request, [
            'valid' => true,
            'receipt_number' => $receipt->receipt_number,
            'organisation_name' => $receipt->organisation->name,
            'payment_date' => (match (true) {
                $receipt->contribution_payment_id !== null => $receipt->payment->payment_date,
                $receipt->loan_repayment_id !== null => $receipt->loanRepayment->repayment_date,
                default => $receipt->welfareBenefitPayment->payment_date,
            })->toDateString(),
            'amount_minor' => $receipt->amount_minor,
            'currency_code' => $receipt->currency_code,
            'payment_method' => $receipt->payment_method,
            'membership_number_masked' => $this->mask($receipt->member->membership_number),
            'status' => $receipt->status->value,
            'transaction_number' => $receipt->financialTransaction->transaction_number,
            'issued_at' => $receipt->issued_at->toIso8601String(),
        ]);
    }

    private function mask(string $value): string
    {
        return mb_strlen($value) <= 4
            ? str_repeat('*', mb_strlen($value))
            : mb_substr($value, 0, 2).str_repeat('*', mb_strlen($value) - 4).mb_substr($value, -2);
    }
}
