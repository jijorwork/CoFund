<?php

namespace App\Domain\Reconciliation\Actions;

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
use App\Domain\Reconciliation\Enums\CashVarianceResolutionType;
use App\Domain\Reconciliation\Enums\CashVarianceStatus;
use App\Domain\Reconciliation\Enums\CashVarianceType;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Models\User;
use Carbon\CarbonImmutable;

class WriteOffCashVariance
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private PostFinancialTransaction $posting,
    ) {}

    public function handle(User $actor, CollectorCashVariance $variance, string $reason, string $idempotencyKey): CollectorCashVariance
    {
        $this->authorization->authorize(FoundationPermission::CashVariancesWriteOff->value);
        if ($variance->status !== CashVarianceStatus::Open || $variance->variance_type !== CashVarianceType::Shortage
            || $variance->reported_by_user_id === $actor->getKey() || trim($reason) === '') {
            throw new InvalidReconciliationOperation('Only an independently reviewed shortage can be written off.');
        }
        $expense = LedgerAccount::query()->whereBelongsTo($this->context->organisation())
            ->where('purpose', AccountPurpose::CashShortageExpense)->whereNull('branch_id')->firstOrFail();
        $transaction = $this->posting->handle($actor, new TransactionData(
            branch: $variance->branch,
            transactionType: FinancialTransactionType::CashShortageWriteOff,
            currencyCode: $variance->currency_code,
            transactionDate: CarbonImmutable::today(),
            description: 'Collector cash shortage write-off',
            idempotencyKey: 'cash-variance-write-off:'.$idempotencyKey,
            referenceType: FinancialReferenceType::CashVariance,
            referenceId: $variance->public_id,
        ), [
            new TransactionEntryData($expense, EntryType::Debit, $variance->amount_minor, 'Cash shortage expense'),
            new TransactionEntryData($variance->collectorWallet->ledgerAccount, EntryType::Credit, $variance->amount_minor, 'Collector shortage cleared', null, $variance->collectorWallet),
        ], FoundationPermission::CashVariancesWriteOff->value);
        $variance->update([
            'status' => CashVarianceStatus::WrittenOff,
            'resolution_type' => CashVarianceResolutionType::WrittenOff,
            'resolution_financial_transaction_id' => $transaction->getKey(),
            'resolution_notes' => $reason,
            'resolved_at' => now(),
            'resolved_by_user_id' => $actor->getKey(),
        ]);

        return $variance->refresh();
    }
}
