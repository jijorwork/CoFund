<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Expenses\Enums\ExpenseSequenceType;
use App\Domain\Expenses\Enums\PettyCashAdvanceStatus;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Expenses\Services\ExpenseNumberGenerator;
use App\Domain\Expenses\Services\ExpenseScope;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManagePettyCashAdvance
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private ExpenseScope $scope, private ExpenseNumberGenerator $numbers, private CalculateWalletBalance $balances, private PostFinancialTransaction $posting, private ReverseFinancialTransaction $reversals) {}

    public function create(User $actor, ?Branch $branch, User $custodian, User $recipient, Wallet $pettyWallet, Wallet $fundingWallet, int $amountMinor, string $purpose, ?CarbonImmutable $dueDate = null): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashRequest->value);
        $org = $this->context->organisation();
        $this->scope->assertBranch($branch?->getKey());
        $activeUsers = OrganisationUser::query()->whereBelongsTo($org)
            ->whereIn('user_id', [$custodian->getKey(), $recipient->getKey()])
            ->where('status', 'active')->distinct()->count('user_id');
        if ($activeUsers !== count(array_unique([$custodian->getKey(), $recipient->getKey()])) || $amountMinor <= 0 || $pettyWallet->organisation_id !== $org->getKey() || $fundingWallet->organisation_id !== $org->getKey() || $pettyWallet->branch_id !== $branch?->getKey() || $pettyWallet->wallet_type !== WalletType::PettyCash || $pettyWallet->status !== WalletStatus::Active || $pettyWallet->currency_code !== $org->currency_code || $fundingWallet->currency_code !== $org->currency_code) {
            throw new InvalidExpenseOperation;
        }

        return DB::transaction(fn (): PettyCashAdvance => PettyCashAdvance::query()->create([
            'organisation_id' => $org->getKey(), 'branch_id' => $branch?->getKey(),
            'advance_number' => $this->numbers->next($org, ExpenseSequenceType::Advance, now($org->timezone)->year),
            'custodian_user_id' => $custodian->getKey(), 'recipient_user_id' => $recipient->getKey(),
            'petty_cash_wallet_id' => $pettyWallet->getKey(), 'funding_wallet_id' => $fundingWallet->getKey(),
            'requested_amount_minor' => $amountMinor, 'currency_code' => $org->currency_code,
            'purpose' => $purpose, 'status' => PettyCashAdvanceStatus::Draft,
            'requested_at' => now(), 'requested_by_user_id' => $actor->getKey(),
            'retirement_due_date' => $dueDate,
        ]), attempts: 3);
    }

    public function submit(User $actor, PettyCashAdvance $advance): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashRequest->value);
        if ($advance->status !== PettyCashAdvanceStatus::Draft || $advance->requested_by_user_id !== $actor->getKey()) {
            throw new InvalidExpenseOperation;
        }$advance->update(['status' => PettyCashAdvanceStatus::Submitted]);

        return $advance->refresh();
    }

    public function approve(User $actor, PettyCashAdvance $advance, int $amountMinor): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashApprove->value);
        if ($advance->status !== PettyCashAdvanceStatus::Submitted || $advance->requested_by_user_id === $actor->getKey() || $amountMinor <= 0 || $amountMinor > $advance->requested_amount_minor) {
            throw new InvalidExpenseOperation;
        }$advance->update(['status' => PettyCashAdvanceStatus::Approved, 'approved_amount_minor' => $amountMinor, 'approved_at' => now(), 'approved_by_user_id' => $actor->getKey()]);

        return $advance->refresh();
    }

    public function reject(User $actor, PettyCashAdvance $advance, string $reason): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashApprove->value);
        if ($advance->status !== PettyCashAdvanceStatus::Submitted || $advance->requested_by_user_id === $actor->getKey() || trim($reason) === '') {
            throw new InvalidExpenseOperation;
        }$advance->update(['status' => PettyCashAdvanceStatus::Rejected, 'rejection_reason' => $reason]);

        return $advance->refresh();
    }

    public function disburse(User $actor, PettyCashAdvance $advance, string $idempotencyKey): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashDisburse->value);

        return DB::transaction(function () use ($actor, $advance, $idempotencyKey): PettyCashAdvance {
            $locked = PettyCashAdvance::query()->with(['organisation', 'branch', 'pettyCashWallet.ledgerAccount'])->lockForUpdate()->whereKey($advance->getKey())->firstOrFail();
            if ($locked->status === PettyCashAdvanceStatus::Disbursed && $locked->idempotency_key === $idempotencyKey) {
                return $locked;
            }
            if ($locked->status !== PettyCashAdvanceStatus::Approved || in_array($actor->getKey(), [$locked->requested_by_user_id, $locked->approved_by_user_id], true) || $this->balances->calculate($locked->pettyCashWallet)->displayBalanceMinor < $locked->approved_amount_minor) {
                throw new InvalidExpenseOperation('The advance cannot be disbursed.');
            }
            $receivable = LedgerAccount::query()->whereBelongsTo($this->context->organisation())->where('purpose', AccountPurpose::StaffCashAdvanceReceivable)->whereNull('branch_id')->firstOrFail();
            $transaction = $this->posting->handle($actor, new TransactionData($locked->branch, FinancialTransactionType::PettyCashAdvance, $locked->currency_code, CarbonImmutable::today(), 'Petty cash advance '.$locked->advance_number, 'petty-cash-advance:'.$idempotencyKey, null, FinancialReferenceType::PettyCashAdvance, $locked->public_id), [new TransactionEntryData($receivable, EntryType::Debit, $locked->approved_amount_minor, 'Staff cash advance'), new TransactionEntryData($locked->pettyCashWallet->ledgerAccount, EntryType::Credit, $locked->approved_amount_minor, 'Petty cash issued', null, $locked->pettyCashWallet)], FoundationPermission::PettyCashDisburse->value);
            $locked->update(['status' => PettyCashAdvanceStatus::Disbursed, 'disbursed_amount_minor' => $locked->approved_amount_minor, 'disbursed_at' => now(), 'disbursed_by_user_id' => $actor->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => hash('sha256', $locked->public_id.'|'.$locked->approved_amount_minor)]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reverse(User $actor, PettyCashAdvance $advance, string $reason, string $idempotencyKey): PettyCashAdvance
    {
        $this->authorization->authorize(FoundationPermission::PettyCashReverse->value);
        if ($advance->status === PettyCashAdvanceStatus::Reversed && $advance->reversal_reason === $reason) {
            return $advance;
        }
        if ($advance->status !== PettyCashAdvanceStatus::Disbursed || $advance->retired_amount_minor > 0 || trim($reason) === '' || $advance->disbursed_by_user_id === $actor->getKey()) {
            throw new InvalidExpenseOperation('The advance cannot be reversed.');
        }
        $this->reversals->handle($advance->financialTransaction, $actor, $reason, 'petty-cash-advance-reversal:'.$idempotencyKey, FoundationPermission::PettyCashReverse->value);
        $advance->update(['status' => PettyCashAdvanceStatus::Reversed, 'reversal_reason' => $reason]);

        return $advance->refresh();
    }
}
