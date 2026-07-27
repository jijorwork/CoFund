<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Expenses\Models\PettyCashRetirementItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $expense_date */
#[Fillable(['public_id', 'organisation_id', 'petty_cash_retirement_id', 'expense_category_id', 'ledger_account_id', 'description', 'amount_minor', 'expense_date', 'sequence_number'])]
class PettyCashRetirementItem extends Model
{
    /** @use HasFactory<PettyCashRetirementItemFactory> */
    use HasFactory, HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<PettyCashRetirement, $this> */
    public function retirement(): BelongsTo
    {
        return $this->belongsTo(PettyCashRetirement::class, 'petty_cash_retirement_id');
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expense_date' => 'date'];
    }
}
