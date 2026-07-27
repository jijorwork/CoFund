<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Enums\PaymentAllocationStatus;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Services\AllocateContributionPayment;
use App\Domain\Collections\Services\ApplyPaymentAllocationToObligation;
use App\Domain\Collections\Services\ContributionPaymentFingerprint;
use App\Domain\Collections\Services\PaymentNumberGenerator;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Enums\WalletType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordContributionPayment
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private ContributionPaymentFingerprint $fingerprints,
        private AllocateContributionPayment $allocator,
        private ApplyPaymentAllocationToObligation $satisfaction,
        private PostFinancialTransaction $posting,
        private PaymentNumberGenerator $numbers,
        private IssueContributionReceipt $receipts,
    ) {}

    public function handle(User $actor, ContributionPaymentData $data): ContributionPayment
    {
        $this->authorization->authorize(FoundationPermission::ContributionPaymentsCollect->value);
        $this->authorizeMethod($data->paymentMethod);
        if ($data->allocationStrategy !== PaymentAllocationStrategy::LeaveUnallocated) {
            $this->authorization->authorize(FoundationPermission::ContributionPaymentsAllocate->value);
        }
        $fingerprint = $this->fingerprints->make($data);
        $this->validate($data);

        return DB::transaction(function () use ($actor, $data, $fingerprint): ContributionPayment {
            $existing = ContributionPayment::query()
                ->whereBelongsTo($this->context->organisation())
                ->where('idempotency_key', $data->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidContributionPayment('The idempotency key was reused with a different payload.');
                }

                return $this->load($existing);
            }

            $session = null;

            if ($data->sourceWallet->wallet_type === WalletType::CollectorCash) {
                $session = CollectorCollectionSession::query()
                    ->whereBelongsTo($this->context->organisation())
                    ->where('collector_wallet_id', $data->sourceWallet->getKey())
                    ->where('collector_user_id', $actor->getKey())
                    ->where('status', CollectorSessionStatus::Open)
                    ->lockForUpdate()
                    ->first();

                if ($session === null) {
                    throw new InvalidContributionPayment('An open collection session is required for collector cash.');
                }
            }

            $allocation = $this->allocator->allocate($data);
            $publicId = (string) Str::uuid7();
            $transaction = $this->posting->handle(
                $actor,
                new TransactionData(
                    branch: $data->sourceWallet->branch,
                    transactionType: FinancialTransactionType::ContributionPayment,
                    currencyCode: $data->currencyCode,
                    transactionDate: $data->paymentDate,
                    description: 'Contribution payment for '.$data->member->membership_number,
                    idempotencyKey: 'contribution-payment:'.$data->idempotencyKey,
                    externalReference: $data->externalReference,
                    referenceType: FinancialReferenceType::ContributionPayment,
                    referenceId: $publicId,
                    metadata: ['member_uuid' => $data->member->public_id],
                ),
                [
                    new TransactionEntryData(
                        $data->sourceWallet->ledgerAccount,
                        EntryType::Debit,
                        $data->amountMinor,
                        'Contribution payment source',
                        null,
                        $data->sourceWallet,
                    ),
                    new TransactionEntryData(
                        $data->destinationWallet->ledgerAccount,
                        EntryType::Credit,
                        $data->amountMinor,
                        'Member savings credit',
                        $data->member,
                        $data->destinationWallet,
                    ),
                ],
                FoundationPermission::ContributionPaymentsCollect->value,
            );
            $payment = ContributionPayment::query()->create([
                'public_id' => $publicId,
                'organisation_id' => $this->context->organisation()->getKey(),
                'branch_id' => $data->sourceWallet->branch_id,
                'member_id' => $data->member->getKey(),
                'contribution_plan_id' => $data->plan?->getKey(),
                'member_plan_enrolment_id' => $data->enrolment?->getKey(),
                'financial_transaction_id' => $transaction->getKey(),
                'payment_number' => $this->numbers->next(
                    $this->context->organisation(),
                    $data->paymentDate->year,
                ),
                'payment_method' => $data->paymentMethod,
                'source_wallet_id' => $data->sourceWallet->getKey(),
                'destination_wallet_id' => $data->destinationWallet->getKey(),
                'amount_minor' => $data->amountMinor,
                'allocated_amount_minor' => $allocation->allocatedAmountMinor,
                'unallocated_amount_minor' => $allocation->unallocatedAmountMinor,
                'currency_code' => strtoupper($data->currencyCode),
                'payment_date' => $data->paymentDate->toDateString(),
                'received_at' => now(),
                'status' => $this->status($allocation->allocatedAmountMinor, $allocation->unallocatedAmountMinor),
                'external_reference' => $data->externalReference,
                'payer_name' => $data->payerName,
                'payer_phone' => $data->payerPhone,
                'collected_by_user_id' => $actor->getKey(),
                'collector_wallet_id' => $data->sourceWallet->wallet_type->value === 'collector_cash'
                    ? $data->sourceWallet->getKey()
                    : null,
                'collector_collection_session_id' => $session?->getKey(),
                'notes' => $data->notes,
                'idempotency_key' => $data->idempotencyKey,
                'request_fingerprint' => $fingerprint,
            ]);

            foreach ($allocation->allocations as $index => $item) {
                ContributionPaymentAllocation::query()->create([
                    'organisation_id' => $payment->organisation_id,
                    'contribution_payment_id' => $payment->getKey(),
                    'contribution_obligation_id' => $item->obligation->getKey(),
                    'member_plan_enrolment_id' => $data->enrolment?->getKey(),
                    'member_id' => $data->member->getKey(),
                    'amount_minor' => $item->amountMinor,
                    'currency_code' => strtoupper($data->currencyCode),
                    'allocation_order' => $index + 1,
                    'status' => PaymentAllocationStatus::Active,
                    'allocated_at' => now(),
                    'created_by_user_id' => $actor->getKey(),
                ]);
                $this->satisfaction->recalculate($item->obligation);
            }

            $receipt = $this->receipts->handle($payment->load('organisation'), $actor);
            $payment->update(['receipt_id' => $receipt->getKey()]);

            return $this->load($payment);
        }, attempts: 3);
    }

    private function validate(ContributionPaymentData $data): void
    {
        $organisation = $this->context->organisation();

        if (
            trim($data->idempotencyKey) === ''
            || $data->amountMinor <= 0
            || $data->member->status !== MemberStatus::Active
            || $data->member->organisation_id !== $organisation->getKey()
            || $data->sourceWallet->organisation_id !== $organisation->getKey()
            || $data->destinationWallet->organisation_id !== $organisation->getKey()
            || strtoupper($data->currencyCode) !== strtoupper($organisation->currency_code)
            || strtoupper($data->sourceWallet->currency_code) !== strtoupper($data->currencyCode)
            || strtoupper($data->destinationWallet->currency_code) !== strtoupper($data->currencyCode)
            || ($data->enrolment !== null && (
                $data->enrolment->organisation_id !== $organisation->getKey()
                || $data->enrolment->member_id !== $data->member->getKey()
                || $data->enrolment->status !== EnrolmentStatus::Active
            ))
            || ($data->plan !== null && (
                $data->enrolment === null
                || $data->enrolment->contribution_plan_id !== $data->plan->getKey()
            ))
        ) {
            throw new InvalidContributionPayment;
        }
    }

    private function authorizeMethod(ContributionPaymentMethod $method): void
    {
        $permission = match ($method) {
            ContributionPaymentMethod::Cash => FoundationPermission::ContributionPaymentsCollectCash,
            ContributionPaymentMethod::BankTransfer,
            ContributionPaymentMethod::Cheque,
            ContributionPaymentMethod::CardManual => FoundationPermission::ContributionPaymentsRecordBank,
            ContributionPaymentMethod::MobileMoneyManual => FoundationPermission::ContributionPaymentsRecordMobileMoney,
            ContributionPaymentMethod::Adjustment => throw new InvalidContributionPayment,
        };

        $this->authorization->authorize($permission->value);
    }

    private function status(int $allocated, int $unallocated): ContributionPaymentStatus
    {
        return match (true) {
            $allocated === 0 => ContributionPaymentStatus::Unallocated,
            $unallocated === 0 => ContributionPaymentStatus::Allocated,
            default => ContributionPaymentStatus::PartiallyAllocated,
        };
    }

    private function load(ContributionPayment $payment): ContributionPayment
    {
        return $payment->refresh()->load([
            'member', 'contributionPlan', 'enrolment', 'financialTransaction',
            'sourceWallet', 'destinationWallet', 'allocations.obligation', 'receipt',
            'collectedBy', 'reversalPayment',
            'session',
        ]);
    }
}
