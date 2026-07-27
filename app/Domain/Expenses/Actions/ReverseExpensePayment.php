<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Expenses\Enums\ExpensePaymentStatus;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpensePayment;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseExpensePayment
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reversals) {}

    public function handle(User $actor, ExpensePayment $payment, string $reason, string $idempotencyKey): ExpensePayment
    {
        $this->authorization->authorize(FoundationPermission::ExpensePaymentsReverse->value);

        return DB::transaction(function () use ($actor, $payment, $reason, $idempotencyKey): ExpensePayment {
            $locked = ExpensePayment::query()->with(['financialTransaction', 'expenseRequest'])->lockForUpdate()->whereKey($payment->getKey())->firstOrFail();
            if ($locked->status === ExpensePaymentStatus::Reversed && $locked->reversal_reason === $reason) {
                return $locked;
            }
            if ($locked->status !== ExpensePaymentStatus::Posted || trim($reason) === '' || $locked->paid_by_user_id === $actor->getKey()) {
                throw new InvalidExpenseOperation('The payment cannot be reversed by this actor.');
            }
            $this->reversals->handle($locked->financialTransaction, $actor, $reason, 'expense-payment-reversal:'.$idempotencyKey, FoundationPermission::ExpensePaymentsReverse->value);
            $locked->update(['status' => ExpensePaymentStatus::Reversed, 'reversal_reason' => $reason]);
            $request = $locked->expenseRequest;
            $paid = max(0, $request->paid_amount_minor - $locked->amount_minor);
            $outstanding = $request->approved_amount_minor - $paid;
            $request->update(['paid_amount_minor' => $paid, 'outstanding_amount_minor' => $outstanding, 'status' => $paid === 0 ? ExpenseRequestStatus::Approved : ExpenseRequestStatus::PartiallyPaid]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
