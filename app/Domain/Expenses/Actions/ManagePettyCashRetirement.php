<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Expenses\Enums\ExpenseSequenceType;
use App\Domain\Expenses\Enums\PettyCashAdvanceStatus;
use App\Domain\Expenses\Enums\PettyCashRetirementStatus;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Expenses\Models\PettyCashRetirement;
use App\Domain\Expenses\Models\PettyCashRetirementItem;
use App\Domain\Expenses\Services\ExpenseNumberGenerator;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManagePettyCashRetirement
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private ExpenseNumberGenerator $numbers, private PostFinancialTransaction $posting) {}

    /** @param list<array{category:ExpenseCategory,ledger_account:LedgerAccount,description:string,amount_minor:int,expense_date:CarbonImmutable}> $items */
    public function create(User $actor, PettyCashAdvance $advance, array $items, int $returnedCashMinor): PettyCashRetirement
    {
        $this->authorization->authorize(FoundationPermission::PettyCashRetire->value);
        if (! in_array($advance->status, [PettyCashAdvanceStatus::Disbursed, PettyCashAdvanceStatus::PartiallyRetired], true) || $advance->recipient_user_id !== $actor->getKey() || $items === [] || $returnedCashMinor < 0) {
            throw new InvalidExpenseOperation;
        }
        $expenses = 0;
        foreach ($items as $item) {
            if ($item['amount_minor'] <= 0 || $item['category']->organisation_id !== $advance->organisation_id || $item['ledger_account']->organisation_id !== $advance->organisation_id || $item['ledger_account']->category !== AccountCategory::Expense) {
                throw new InvalidExpenseOperation;
            }$expenses += $item['amount_minor'];
        }
        $outstanding = $advance->disbursed_amount_minor - $advance->retired_amount_minor;
        if ($expenses + $returnedCashMinor > $outstanding) {
            throw new InvalidExpenseOperation('Retirement exceeds the outstanding advance.');
        }

        return DB::transaction(function () use ($actor, $advance, $items, $returnedCashMinor, $expenses): PettyCashRetirement {
            $retirement = PettyCashRetirement::query()->create(['organisation_id' => $advance->organisation_id, 'branch_id' => $advance->branch_id, 'petty_cash_advance_id' => $advance->getKey(), 'retirement_number' => $this->numbers->next($advance->organisation, ExpenseSequenceType::Retirement, now($advance->organisation->timezone)->year), 'submitted_amount_minor' => $expenses, 'returned_cash_minor' => $returnedCashMinor, 'currency_code' => $advance->currency_code, 'status' => PettyCashRetirementStatus::Draft, 'submitted_by_user_id' => $actor->getKey()]);
            foreach ($items as $index => $item) {
                PettyCashRetirementItem::query()->create(['organisation_id' => $advance->organisation_id, 'petty_cash_retirement_id' => $retirement->getKey(), 'expense_category_id' => $item['category']->getKey(), 'ledger_account_id' => $item['ledger_account']->getKey(), 'description' => $item['description'], 'amount_minor' => $item['amount_minor'], 'expense_date' => $item['expense_date'], 'sequence_number' => $index + 1]);
            }

            return $retirement->load(['advance', 'items.category', 'items.ledgerAccount']);
        }, attempts: 3);
    }

    public function submit(User $actor, PettyCashRetirement $retirement): PettyCashRetirement
    {
        $this->authorization->authorize(FoundationPermission::PettyCashRetire->value);
        if ($retirement->status !== PettyCashRetirementStatus::Draft || $retirement->submitted_by_user_id !== $actor->getKey()) {
            throw new InvalidExpenseOperation;
        }$retirement->update(['status' => PettyCashRetirementStatus::Submitted, 'submitted_at' => now()]);

        return $retirement->refresh();
    }

    public function approve(User $actor, PettyCashRetirement $retirement, int $acceptedExpenseMinor, int $returnedCashMinor, string $idempotencyKey): PettyCashRetirement
    {
        $this->authorization->authorize(FoundationPermission::PettyCashReviewRetirement->value);

        return DB::transaction(function () use ($actor, $retirement, $acceptedExpenseMinor, $returnedCashMinor, $idempotencyKey): PettyCashRetirement {
            $locked = PettyCashRetirement::query()->with(['advance.organisation', 'advance.branch', 'advance.pettyCashWallet.ledgerAccount', 'items.ledgerAccount'])->lockForUpdate()->whereKey($retirement->getKey())->firstOrFail();
            if ($locked->status !== PettyCashRetirementStatus::Submitted || $locked->submitted_by_user_id === $actor->getKey() || $acceptedExpenseMinor !== $locked->items->sum('amount_minor') || $returnedCashMinor < 0) {
                throw new InvalidExpenseOperation;
            }
            $outstanding = $locked->advance->disbursed_amount_minor - $locked->advance->retired_amount_minor;
            $cleared = $acceptedExpenseMinor + $returnedCashMinor;
            if ($cleared > $outstanding) {
                throw new InvalidExpenseOperation;
            }
            $entries = [];
            foreach ($locked->items->groupBy('ledger_account_id') as $items) {
                $entries[] = new TransactionEntryData($items->first()->ledgerAccount, EntryType::Debit, $items->sum('amount_minor'), 'Retired petty cash expense');
            }
            if ($returnedCashMinor > 0) {
                $entries[] = new TransactionEntryData($locked->advance->pettyCashWallet->ledgerAccount, EntryType::Debit, $returnedCashMinor, 'Unused petty cash returned', null, $locked->advance->pettyCashWallet);
            }
            $receivable = LedgerAccount::query()->whereBelongsTo($this->context->organisation())->where('purpose', AccountPurpose::StaffCashAdvanceReceivable)->whereNull('branch_id')->firstOrFail();
            $entries[] = new TransactionEntryData($receivable, EntryType::Credit, $cleared, 'Staff cash advance retired');
            $transaction = $this->posting->handle($actor, new TransactionData($locked->advance->branch, FinancialTransactionType::PettyCashRetirement, $locked->currency_code, CarbonImmutable::today(), 'Petty cash retirement '.$locked->retirement_number, 'petty-cash-retirement:'.$idempotencyKey, null, FinancialReferenceType::PettyCashRetirement, $locked->public_id), $entries, FoundationPermission::PettyCashReviewRetirement->value);
            $shortage = $outstanding - $cleared;
            $locked->update(['accepted_expense_minor' => $acceptedExpenseMinor, 'returned_cash_minor' => $returnedCashMinor, 'shortage_minor' => $shortage, 'status' => PettyCashRetirementStatus::Approved, 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->getKey(), 'approved_at' => now(), 'approved_by_user_id' => $actor->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => hash('sha256', $locked->public_id.'|'.$cleared)]);
            $retired = $locked->advance->retired_amount_minor + $cleared;
            $locked->advance->update(['retired_amount_minor' => $retired, 'refundable_amount_minor' => $returnedCashMinor, 'shortage_minor' => $shortage, 'status' => $shortage === 0 && $retired === $locked->advance->disbursed_amount_minor ? PettyCashAdvanceStatus::Retired : PettyCashAdvanceStatus::PartiallyRetired, 'retired_at' => $shortage === 0 ? now() : null, 'retired_by_user_id' => $actor->getKey()]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, PettyCashRetirement $retirement, string $reason): PettyCashRetirement
    {
        $this->authorization->authorize(FoundationPermission::PettyCashReviewRetirement->value);
        if ($retirement->status !== PettyCashRetirementStatus::Submitted || $retirement->submitted_by_user_id === $actor->getKey() || trim($reason) === '') {
            throw new InvalidExpenseOperation;
        }$retirement->update(['status' => PettyCashRetirementStatus::Rejected, 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->getKey(), 'rejection_reason' => $reason]);

        return $retirement->refresh();
    }
}
