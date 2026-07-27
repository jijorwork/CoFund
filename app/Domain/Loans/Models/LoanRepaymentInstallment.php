<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\InstallmentSatisfactionStatus;
use App\Domain\Loans\Enums\InstallmentTimingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property InstallmentTimingStatus $timing_status
 * @property InstallmentSatisfactionStatus $satisfaction_status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $due_date
 * @property Carbon $grace_due_date
 */
#[Fillable(['public_id', 'organisation_id', 'loan_id', 'sequence_number', 'period_start', 'period_end', 'due_date', 'grace_due_date', 'opening_principal_minor', 'scheduled_principal_minor', 'scheduled_interest_minor', 'scheduled_fees_minor', 'scheduled_penalties_minor', 'paid_principal_minor', 'paid_interest_minor', 'paid_fees_minor', 'paid_penalties_minor', 'timing_status', 'satisfaction_status', 'schedule_status'])]
class LoanRepaymentInstallment extends Model
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function totalScheduledMinor(): int
    {
        return $this->scheduled_principal_minor + $this->scheduled_interest_minor + $this->scheduled_fees_minor + $this->scheduled_penalties_minor;
    }

    public function totalPaidMinor(): int
    {
        return $this->paid_principal_minor + $this->paid_interest_minor + $this->paid_fees_minor + $this->paid_penalties_minor;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['timing_status' => InstallmentTimingStatus::class, 'satisfaction_status' => InstallmentSatisfactionStatus::class, 'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'grace_due_date' => 'date'];
    }
}
