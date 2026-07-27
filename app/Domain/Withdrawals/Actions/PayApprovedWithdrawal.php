<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Domain\Withdrawals\Enums\SavingsHoldStatus;
use App\Domain\Withdrawals\Enums\WithdrawalPayoutStatus;
use App\Domain\Withdrawals\Enums\WithdrawalRequestStatus;
use App\Domain\Withdrawals\Enums\WithdrawalSequenceType;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\CalculateWithdrawableSavings;
use App\Domain\Withdrawals\Services\WithdrawalNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayApprovedWithdrawal
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateWithdrawableSavings $withdrawable, private CalculateWalletBalance $balances, private PostFinancialTransaction $posting, private WithdrawalNumberGenerator $numbers, private IssueWithdrawalReceipt $receipts) {}

    public function handle(User $actor, WithdrawalRequest $request, Wallet $destination, int $grossMinor, string $method, CarbonImmutable $date, string $idempotencyKey, string $recipientType, string $recipientName): WithdrawalPayout
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalPayoutsCreate->value);
        $fingerprint = hash('sha256', implode('|', [$request->public_id, $destination->public_id, $grossMinor, $method, $date->toDateString(), $recipientType, $recipientName]));

        return DB::transaction(function () use ($actor, $request, $destination, $grossMinor, $method, $date, $idempotencyKey, $recipientType, $recipientName, $fingerprint): WithdrawalPayout {
            $existing = WithdrawalPayout::query()->where('organisation_id', $this->context->organisation()->getKey())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new InvalidWithdrawalOperation('Idempotency key conflict.');
                }

                return $existing;
            }
            $locked = WithdrawalRequest::query()->with(['member', 'product', 'reason', 'savingsWallet.ledgerAccount', 'reservationHold', 'payouts'])->where('organisation_id', $this->context->organisation()->getKey())->lockForUpdate()->whereKey($request->getKey())->firstOrFail();
            if (! in_array($locked->status, [WithdrawalRequestStatus::Approved, WithdrawalRequestStatus::PartiallyPaid], true) || $grossMinor <= 0 || $grossMinor > $locked->outstanding_approved_minor || (! $locked->product->allow_partial_payout && $grossMinor !== $locked->outstanding_approved_minor)) {
                throw new InvalidWithdrawalOperation('The approved withdrawal cannot be paid.');
            }
            $calculation = $this->withdrawable->calculate($locked->member, $locked->product, $locked->savingsWallet, $locked->reason, $locked);
            if ($grossMinor > $calculation['withdrawable_balance_minor'] || $calculation['blocking_reasons'] !== []) {
                throw new InvalidWithdrawalOperation('Current restrictions prevent the withdrawal payout.');
            }
            if ($destination->organisation_id !== $locked->organisation_id || $destination->branch_id !== $locked->branch_id || $destination->currency_code !== $locked->currency_code || $destination->status !== WalletStatus::Active) {
                throw new InvalidWithdrawalOperation('The destination wallet is not eligible.');
            }
            $postedGross = (int) $locked->payouts->where('status', WithdrawalPayoutStatus::Posted)->sum('gross_withdrawal_minor');
            $postedFee = (int) $locked->payouts->where('status', WithdrawalPayoutStatus::Posted)->sum('fee_minor');
            $isFinal = $postedGross + $grossMinor === $locked->approved_amount_minor;
            $fee = $isFinal ? max(0, (int) $locked->fee_minor - $postedFee) : intdiv((int) $locked->fee_minor * $grossMinor, (int) $locked->approved_amount_minor);
            $net = $grossMinor - $fee;
            if ($net < 0 || $this->balances->calculate($destination)->displayBalanceMinor < $net) {
                throw new InvalidWithdrawalOperation('The payout wallet cannot fund the net withdrawal.');
            }
            $entries = [new TransactionEntryData($locked->savingsWallet->ledgerAccount, EntryType::Debit, $grossMinor, 'Reduce member savings liability', $locked->member, $locked->savingsWallet), new TransactionEntryData($destination->ledgerAccount, EntryType::Credit, $net, 'Withdrawal paid', $locked->member, $destination)];
            if ($fee > 0) {
                $feeAccount = LedgerAccount::query()->where('organisation_id', $locked->organisation_id)->where('purpose', AccountPurpose::WithdrawalFeeIncome)->where('currency_code', $locked->currency_code)->where('status', 'active')->where('allows_direct_posting', true)->first() ?? throw new InvalidWithdrawalOperation('Withdrawal fee income account is unavailable.');
                $entries[] = new TransactionEntryData($feeAccount, EntryType::Credit, $fee, 'Withdrawal fee income', $locked->member);
            }
            $transaction = $this->posting->handle($actor, new TransactionData($locked->branch, FinancialTransactionType::Withdrawal, $locked->currency_code, $date, 'Savings withdrawal '.$locked->request_number, 'withdrawal-payout:'.$idempotencyKey, null, FinancialReferenceType::WithdrawalRequest, $locked->public_id), $entries, FoundationPermission::WithdrawalPayoutsCreate->value);
            $payout = WithdrawalPayout::query()->create(['organisation_id' => $locked->organisation_id, 'branch_id' => $locked->branch_id, 'withdrawal_request_id' => $locked->getKey(), 'payout_number' => $this->numbers->next($this->context->organisation(), WithdrawalSequenceType::Payout, $date->year), 'member_savings_wallet_id' => $locked->savings_wallet_id, 'destination_wallet_id' => $destination->getKey(), 'recipient_type' => $recipientType, 'recipient_name' => $recipientName, 'gross_withdrawal_minor' => $grossMinor, 'fee_minor' => $fee, 'net_payout_minor' => $net, 'currency_code' => $locked->currency_code, 'payment_method' => $method, 'payout_date' => $date, 'financial_transaction_id' => $transaction->getKey(), 'paid_by_user_id' => $actor->getKey(), 'approved_by_user_id' => $locked->approved_by_user_id, 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint]);
            $receipt = $this->receipts->handle($payout->load('request'), $actor);
            $payout->update(['receipt_id' => $receipt->getKey()]);
            $outstanding = max(0, (int) $locked->outstanding_approved_minor - $grossMinor);
            $locked->reservationHold?->update($outstanding === 0 ? ['amount_minor' => 0, 'status' => SavingsHoldStatus::Released, 'released_at' => now(), 'released_by_user_id' => $actor->getKey(), 'release_reason' => 'Withdrawal fully paid'] : ['amount_minor' => $outstanding]);
            $locked->update(['total_paid_minor' => (int) $locked->total_paid_minor + $grossMinor, 'outstanding_approved_minor' => $outstanding, 'payment_status' => $outstanding === 0 ? 'paid' : 'partially_paid', 'status' => $outstanding === 0 ? WithdrawalRequestStatus::Settled : WithdrawalRequestStatus::PartiallyPaid, 'settled_at' => $outstanding === 0 ? now() : null]);

            return $payout->refresh()->load(['request', 'destinationWallet', 'financialTransaction', 'receipt']);
        }, attempts: 3);
    }
}
