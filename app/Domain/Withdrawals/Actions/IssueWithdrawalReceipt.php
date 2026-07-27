<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\ReceiptNumberGenerator;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Models\User;
use Illuminate\Support\Str;

class IssueWithdrawalReceipt
{
    public function __construct(private TenantContext $context, private ReceiptNumberGenerator $numbers) {}

    public function handle(WithdrawalPayout $payout, User $actor): Receipt
    {
        $code = Str::random(48);

        return Receipt::query()->create(['organisation_id' => $payout->organisation_id, 'branch_id' => $payout->branch_id, 'withdrawal_payout_id' => $payout->getKey(), 'financial_transaction_id' => $payout->financial_transaction_id, 'receipt_number' => $this->numbers->next($this->context->organisation(), $payout->payout_date->year), 'receipt_type' => ReceiptType::WithdrawalPayout, 'status' => ReceiptStatus::Issued, 'issued_at' => now(), 'issued_by_user_id' => $actor->getKey(), 'member_id' => $payout->request->member_id, 'payer_name' => $payout->recipient_name, 'payment_method' => $payout->payment_method, 'amount_minor' => $payout->net_payout_minor, 'currency_code' => $payout->currency_code, 'verification_code' => $code, 'verification_hash' => hash('sha256', $code), 'metadata' => ['request_number' => $payout->request->request_number, 'payout_number' => $payout->payout_number, 'gross_minor' => $payout->gross_withdrawal_minor, 'fee_minor' => $payout->fee_minor]]);
    }
}
