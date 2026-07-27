<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\InstallmentSatisfactionStatus;
use App\Domain\Loans\Enums\LoanRepaymentComponent;
use App\Domain\Loans\Enums\LoanRepaymentStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Loans\Services\RecalculateLoanPosition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReverseLoanRepayment
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reverseTransaction, private RecalculateLoanPosition $positions) {}

    public function handle(User $actor, LoanRepayment $repayment, string $reason, string $idempotencyKey): LoanRepayment
    {
        $this->authorization->authorize(FoundationPermission::LoanRepaymentsReverse->value);
        if (trim($reason) === '') {
            throw new InvalidLoanOperation('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $repayment, $reason, $idempotencyKey): LoanRepayment {
            $locked = LoanRepayment::query()->with(['financialTransaction', 'receipt', 'loan.product', 'allocations.installment'])->lockForUpdate()->whereKey($repayment->getKey())->firstOrFail();
            if ($locked->status === LoanRepaymentStatus::Reversed) {
                return $locked;
            }
            $reversal = $this->reverseTransaction->handle($locked->financialTransaction, $actor, $reason, 'loan-repayment-reversal:'.$idempotencyKey, FoundationPermission::LoanRepaymentsReverse->value);
            foreach ($locked->allocations->where('status', 'active') as $allocation) {
                $column = match ($allocation->component) {
                    LoanRepaymentComponent::Fee => 'fees', LoanRepaymentComponent::Penalty => 'penalties', default => $allocation->component->value
                };
                $allocation->installment->decrement('paid_'.$column.'_minor', $allocation->amount_minor);
                $allocation->installment->refresh();
                $allocation->installment->update(['satisfaction_status' => $allocation->installment->totalPaidMinor() === 0 ? InstallmentSatisfactionStatus::Unsatisfied : InstallmentSatisfactionStatus::PartiallySatisfied]);
                $allocation->update(['status' => 'reversed']);
            }
            $locked->update(['status' => LoanRepaymentStatus::Reversed, 'reversal_transaction_id' => $reversal->getKey(), 'reversal_reason' => $reason, 'reversed_at' => now()]);
            $locked->receipt?->update(['status' => ReceiptStatus::Reversed]);
            if ($locked->loan->status === LoanStatus::Settled) {
                $locked->loan->update(['status' => LoanStatus::Active]);
            }
            $this->positions->recalculate($locked->loan, CarbonImmutable::now($locked->loan->application->organisation->timezone)->startOfDay());

            return $locked->refresh();
        }, attempts: 3);
    }
}
