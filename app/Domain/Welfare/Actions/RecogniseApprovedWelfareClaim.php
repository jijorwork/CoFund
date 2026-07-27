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
use App\Domain\Welfare\Enums\WelfareApprovalStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfarePaymentStatus;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimLiability;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;
use App\Domain\Welfare\Services\WelfareNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecogniseApprovedWelfareClaim
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private EvaluateWelfareEligibility $eligibility, private PostFinancialTransaction $posting, private WelfareNumberGenerator $numbers) {}

    public function handle(User $actor, WelfareClaim $claim, CarbonImmutable $date, string $idempotencyKey): WelfareClaimLiability
    {
        $this->authorization->authorize(FoundationPermission::WelfareLiabilitiesRecognise->value);
        $fingerprint = hash('sha256', implode('|', [$claim->public_id, (int) $claim->approved_benefit_minor, $date->toDateString()]));

        return DB::transaction(function () use ($actor, $claim, $date, $idempotencyKey, $fingerprint): WelfareClaimLiability {
            $existing = WelfareClaimLiability::query()->where('organisation_id', $this->context->organisation()->getKey())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidWelfareOperation('Idempotency key conflict.');
                }

                return $existing;
            }
            $locked = WelfareClaim::query()->with(['product', 'member', 'event', 'eventType', 'enrolment'])->where('organisation_id', $this->context->organisation()->getKey())->lockForUpdate()->whereKey($claim->getKey())->firstOrFail();
            $amount = (int) $locked->approved_benefit_minor;
            $lockedFingerprint = hash('sha256', implode('|', [$locked->public_id, $amount, $date->toDateString()]));
            if (! hash_equals($fingerprint, $lockedFingerprint)) {
                throw new InvalidWelfareOperation('The claim changed while liability recognition was in progress.');
            }
            if ($locked->approval_status !== WelfareApprovalStatus::Approved || $amount <= 0 || ! $this->eligibility->evaluate($locked)['eligible'] || $locked->liabilities()->where('status', 'recognised')->exists()) {
                throw new InvalidWelfareOperation('The claim is not ready for liability recognition.');
            }
            $expense = $this->account(AccountPurpose::WelfareBenefitExpense, $locked);
            $payable = $this->account(AccountPurpose::WelfareClaimsPayable, $locked);
            $transaction = $this->posting->handle($actor, new TransactionData($locked->event->branch, FinancialTransactionType::WelfareLiabilityRecognition, $locked->currency_code, $date, 'Welfare claim liability '.$locked->claim_number, 'welfare-liability:'.$idempotencyKey, null, FinancialReferenceType::WelfareClaim, $locked->public_id), [new TransactionEntryData($expense, EntryType::Debit, $amount, 'Welfare benefit expense', $locked->member), new TransactionEntryData($payable, EntryType::Credit, $amount, 'Welfare claims payable', $locked->member)], FoundationPermission::WelfareLiabilitiesRecognise->value);
            $liability = WelfareClaimLiability::query()->create(['organisation_id' => $locked->organisation_id, 'welfare_claim_id' => $locked->getKey(), 'liability_number' => $this->numbers->next($this->context->organisation(), WelfareSequenceType::Liability, $date->year), 'approved_benefit_minor' => $amount, 'currency_code' => $locked->currency_code, 'active_liability_key' => (string) $locked->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'recognised_at' => now(), 'recognised_by_user_id' => $actor->getKey(), 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint]);
            $locked->update(['status' => WelfareClaimStatus::Approved, 'payment_status' => WelfarePaymentStatus::Unpaid, 'liability_recognised_at' => now(), 'liability_transaction_id' => $transaction->getKey(), 'outstanding_payable_minor' => $amount]);

            return $liability->load(['claim', 'financialTransaction']);
        }, attempts: 3);
    }

    private function account(AccountPurpose $purpose, WelfareClaim $claim): LedgerAccount
    {
        return LedgerAccount::query()->where('organisation_id', $claim->organisation_id)->where('purpose', $purpose)->where('currency_code', $claim->currency_code)->where('status', 'active')->where('allows_direct_posting', true)->first() ?? throw new InvalidWelfareOperation('Required welfare ledger account is unavailable.');
    }
}
