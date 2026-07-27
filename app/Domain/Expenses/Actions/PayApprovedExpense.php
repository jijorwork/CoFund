<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Expenses\Enums\ExpensePaymentMethod;
use App\Domain\Expenses\Enums\ExpensePaymentStatus;
use App\Domain\Expenses\Enums\ExpensePaymentType;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Expenses\Enums\ExpenseSequenceType;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\ExpensePayment;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Services\ExpenseNumberGenerator;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayApprovedExpense
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateWalletBalance $balances, private PostFinancialTransaction $posting, private ExpenseNumberGenerator $numbers) {}

    public function handle(User $actor, ExpenseRequest $request, Wallet $source, int $amountMinor, ExpensePaymentType $type, ExpensePaymentMethod $method, CarbonImmutable $date, string $payee, string $idempotencyKey): ExpensePayment
    {
        $this->authorization->authorize(FoundationPermission::ExpensePaymentsPay->value);
        $fingerprint = hash('sha256', implode('|', [$request->public_id, $source->public_id, $amountMinor, $type->value, $method->value, $date->toDateString(), $payee]));

        return DB::transaction(function () use ($actor, $request, $source, $amountMinor, $type, $method, $date, $payee, $idempotencyKey, $fingerprint): ExpensePayment {
            $existing = ExpensePayment::query()->whereBelongsTo($this->context->organisation())->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new InvalidExpenseOperation('Idempotency key conflict.');
                }

                return $existing;
            }
            $locked = ExpenseRequest::query()->with(['category.defaultLedgerAccount', 'items.ledgerAccount', 'items.category.defaultLedgerAccount', 'branch'])->whereBelongsTo($this->context->organisation())->lockForUpdate()->whereKey($request->getKey())->firstOrFail();
            if (! in_array($locked->status, [ExpenseRequestStatus::Approved, ExpenseRequestStatus::PartiallyPaid], true) || $amountMinor <= 0 || $amountMinor > $locked->outstanding_amount_minor || $source->organisation_id !== $locked->organisation_id || ($source->branch_id !== null && $source->branch_id !== $locked->branch_id) || $source->currency_code !== $locked->currency_code || $source->status !== WalletStatus::Active || $this->balances->calculate($source)->displayBalanceMinor < $amountMinor) {
                throw new InvalidExpenseOperation('The approved expense cannot be paid from this wallet.');
            }
            $groups = [];
            $alreadyPaid = $locked->paid_amount_minor;
            $remainingPayment = $amountMinor;
            foreach ($locked->items as $item) {
                if ($alreadyPaid >= $item->line_total_minor) {
                    $alreadyPaid -= $item->line_total_minor;

                    continue;
                }
                $available = $item->line_total_minor - $alreadyPaid;
                $allocated = min($available, $remainingPayment);
                $alreadyPaid = 0;
                if ($allocated === 0) {
                    break;
                }
                $itemCategory = $item->getRelationValue('category');
                $account = $item->ledgerAccount
                    ?? ($itemCategory instanceof ExpenseCategory ? $itemCategory->defaultLedgerAccount : null)
                    ?? $locked->category->defaultLedgerAccount;
                $groups[$account->getKey()] = ['account' => $account, 'amount' => ($groups[$account->getKey()]['amount'] ?? 0) + $allocated];
                $remainingPayment -= $allocated;
            }
            if ($remainingPayment !== 0) {
                throw new InvalidExpenseOperation('Payment allocation exceeds approved expense lines.');
            }
            $entries = [];
            foreach ($groups as $group) {
                $entries[] = new TransactionEntryData($group['account'], EntryType::Debit, $group['amount'], 'Expense: '.$locked->expense_number);
            }
            $entries[] = new TransactionEntryData($source->ledgerAccount, EntryType::Credit, $amountMinor, 'Expense disbursement', null, $source);
            $publicId = (string) Str::uuid7();
            $transaction = $this->posting->handle($actor, new TransactionData($locked->branch, FinancialTransactionType::ExpensePayment, $locked->currency_code, $date, 'Expense payment '.$locked->expense_number, 'expense-payment:'.$idempotencyKey, null, FinancialReferenceType::ExpensePayment, $publicId), $entries, FoundationPermission::ExpensePaymentsPay->value);
            $payment = ExpensePayment::query()->create(['public_id' => $publicId, 'organisation_id' => $locked->organisation_id, 'branch_id' => $locked->branch_id, 'expense_request_id' => $locked->getKey(), 'payment_number' => $this->numbers->next($locked->organisation, ExpenseSequenceType::Payment, $date->year), 'payment_type' => $type, 'source_wallet_id' => $source->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'amount_minor' => $amountMinor, 'currency_code' => $locked->currency_code, 'payment_method' => $method, 'payment_date' => $date, 'payee_name' => $payee, 'status' => ExpensePaymentStatus::Posted, 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint, 'paid_by_user_id' => $actor->getKey(), 'approved_by_user_id' => $locked->approved_by_user_id]);
            $paid = $locked->paid_amount_minor + $amountMinor;
            $outstanding = $locked->approved_amount_minor - $paid;
            $locked->update(['paid_amount_minor' => $paid, 'outstanding_amount_minor' => $outstanding, 'status' => $outstanding === 0 ? ExpenseRequestStatus::Paid : ExpenseRequestStatus::PartiallyPaid]);

            return $payment->load(['expenseRequest', 'sourceWallet', 'financialTransaction']);
        }, attempts: 3);
    }
}
