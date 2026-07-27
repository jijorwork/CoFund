<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\LoanRepaymentComponent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property LoanRepaymentComponent $component */
#[Fillable(['public_id', 'organisation_id', 'loan_repayment_id', 'loan_repayment_installment_id', 'component', 'amount_minor', 'allocation_order', 'status', 'reversed_allocation_id'])]
class LoanRepaymentAllocation extends Model
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<LoanRepayment, $this> */
    public function repayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class, 'loan_repayment_id');
    }

    /** @return BelongsTo<LoanRepaymentInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanRepaymentInstallment::class, 'loan_repayment_installment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['component' => LoanRepaymentComponent::class];
    }
}
