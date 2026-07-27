<?php

namespace App\Domain\Welfare\Actions;

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
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareLiabilityStatus;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use App\Domain\Welfare\Services\RecalculateWelfareClaimPosition;
use App\Domain\Welfare\Services\WelfareNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayWelfareBenefit
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateWalletBalance $balances, private PostFinancialTransaction $posting, private WelfareNumberGenerator $numbers, private IssueWelfarePaymentReceipt $receipts, private RecalculateWelfareClaimPosition $positions) {}

    public function handle(User $actor, WelfareClaim $claim, Wallet $source, ?WelfareClaimBeneficiary $beneficiary, int $amountMinor, string $method, CarbonImmutable $date, string $idempotencyKey, string $recipientType, string $recipientName): WelfareBenefitPayment
    {
        $this->authorization->authorize(FoundationPermission::WelfarePaymentsCreate->value);
        $fingerprint = hash('sha256', implode('|', [$claim->public_id, $source->public_id, $beneficiary?->public_id, $amountMinor, $method, $date->toDateString(), $recipientName]));

        return DB::transaction(function () use ($actor, $claim, $source, $beneficiary, $amountMinor, $method, $date, $idempotencyKey, $recipientType, $recipientName, $fingerprint): WelfareBenefitPayment {
            $existing = WelfareBenefitPayment::query()->where('organisation_id', $this->context->organisation()->getKey())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidWelfareOperation('Idempotency key conflict.');
                }

return $existing;
            }
            $locked = WelfareClaim::query()->with(['product', 'member', 'event.branch', 'liabilities'])->where('organisation_id', $this->context->organisation()->getKey())->lockForUpdate()->whereKey($claim->getKey())->firstOrFail();
            $liability = $locked->liabilities->firstWhere('status', WelfareLiabilityStatus::Recognised);
            if ($liability === null || ! in_array($locked->status, [WelfareClaimStatus::Approved, WelfareClaimStatus::PartiallyPaid], true) || $amountMinor <= 0 || $amountMinor > $locked->outstanding_payable_minor || (! $locked->product->partial_payment_allowed && $amountMinor !== $locked->outstanding_payable_minor)) {
                throw new InvalidWelfareOperation('The welfare payment is not permitted.');
            }
            if ($beneficiary !== null && ($beneficiary->welfare_claim_id !== $locked->getKey() || ! $beneficiary->payment_eligible)) {
                throw new InvalidWelfareOperation('The recipient is not eligible for payment.');
            }
            if ($beneficiary !== null && $beneficiary->allocated_benefit_minor !== null) {
                $paidToRecipient = (int) $locked->payments()->where('welfare_claim_beneficiary_id', $beneficiary->getKey())->where('status', 'posted')->sum('amount_minor');
                if ($paidToRecipient + $amountMinor > $beneficiary->allocated_benefit_minor) {
                    throw new InvalidWelfareOperation('The recipient allocation would be exceeded.');
                }
            }
            if ($source->organisation_id !== $locked->organisation_id || $source->branch_id !== $locked->branch_id || $source->currency_code !== $locked->currency_code || $source->status !== WalletStatus::Active || $this->balances->calculate($source)->displayBalanceMinor < $amountMinor) {
                throw new InvalidWelfareOperation('The source wallet cannot fund this payment.');
            }
            $payable = LedgerAccount::query()->where('organisation_id', $locked->organisation_id)->where('purpose', AccountPurpose::WelfareClaimsPayable)->where('currency_code', $locked->currency_code)->where('status', 'active')->first() ?? throw new InvalidWelfareOperation('Welfare claims payable account is unavailable.');
            $transaction = $this->posting->handle($actor, new TransactionData($locked->event->branch, FinancialTransactionType::WelfarePayment, $locked->currency_code, $date, 'Welfare benefit '.$locked->claim_number, 'welfare-payment:'.$idempotencyKey, null, FinancialReferenceType::WelfareClaim, $locked->public_id), [new TransactionEntryData($payable, EntryType::Debit, $amountMinor, 'Settle welfare payable', $locked->member), new TransactionEntryData($source->ledgerAccount, EntryType::Credit, $amountMinor, 'Welfare benefit paid', $locked->member, $source)], FoundationPermission::WelfarePaymentsCreate->value);
            $payment = WelfareBenefitPayment::query()->create(['organisation_id' => $locked->organisation_id, 'branch_id' => $locked->branch_id, 'welfare_claim_id' => $locked->getKey(), 'welfare_claim_liability_id' => $liability->getKey(), 'welfare_claim_beneficiary_id' => $beneficiary?->getKey(), 'payment_number' => $this->numbers->next($this->context->organisation(), WelfareSequenceType::Payment, $date->year), 'source_wallet_id' => $source->getKey(), 'recipient_type' => $recipientType, 'recipient_name' => $recipientName, 'amount_minor' => $amountMinor, 'currency_code' => $locked->currency_code, 'payment_method' => $method, 'payment_date' => $date, 'financial_transaction_id' => $transaction->getKey(), 'paid_by_user_id' => $actor->getKey(), 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint]);
            $receipt = $this->receipts->handle($payment->load('claim.product.organisation'), $actor);
            $payment->update(['receipt_id' => $receipt->getKey()]);
            $this->positions->recalculate($locked);

            return $payment->refresh()->load(['claim', 'beneficiary', 'sourceWallet', 'financialTransaction', 'receipt']);
        }, attempts: 3);
    }
}
