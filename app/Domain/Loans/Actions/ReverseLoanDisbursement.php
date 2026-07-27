<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\InstallmentTimingStatus;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanRepaymentStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanDisbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseLoanDisbursement
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reverseTransaction) {}

    public function handle(User $actor, LoanDisbursement $disbursement, string $reason, string $idempotencyKey): LoanDisbursement
    {
        $this->authorization->authorize(FoundationPermission::LoansReverseDisbursement->value);
        if (trim($reason) === '') {
            throw new InvalidLoanOperation('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $disbursement, $reason, $idempotencyKey): LoanDisbursement {
            $locked = LoanDisbursement::query()->with(['financialTransaction', 'loan.repayments', 'loan.installments', 'loan.application'])->lockForUpdate()->whereKey($disbursement->getKey())->firstOrFail();
            if ($locked->status === 'reversed') {
                return $locked;
            }
            if ($locked->loan->repayments->where('status', '!=', LoanRepaymentStatus::Reversed)->isNotEmpty()) {
                throw new InvalidLoanOperation('A disbursement with active repayments cannot be reversed.');
            }
            $reversal = $this->reverseTransaction->handle($locked->financialTransaction, $actor, $reason, 'loan-disbursement-reversal:'.$idempotencyKey, FoundationPermission::LoansReverseDisbursement->value);
            $locked->update(['status' => 'reversed', 'reversal_transaction_id' => $reversal->getKey(), 'reversal_reason' => $reason, 'reversed_at' => now()]);
            $locked->loan->installments()->update(['schedule_status' => 'cancelled', 'timing_status' => InstallmentTimingStatus::Cancelled]);
            $locked->loan->update(['status' => LoanStatus::Reversed, 'performance_status' => LoanPerformanceStatus::NotDisbursed]);
            $locked->loan->application->update(['workflow_status' => LoanApplicationStatus::OfferAccepted]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
