<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Expenses\Models\ExpenseRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read ExpenseCategory|null $category
 * @property-read LedgerAccount|null $ledgerAccount
 */
#[Fillable(['public_id', 'organisation_id', 'expense_request_id', 'expense_category_id', 'description', 'quantity', 'unit_amount_minor', 'line_total_minor', 'currency_code', 'ledger_account_id', 'sequence_number'])]
class ExpenseRequestItem extends Model
{
    /** @use HasFactory<ExpenseRequestItemFactory> */
    use HasFactory, HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if ($item->expenseRequest->status !== ExpenseRequestStatus::Draft) {
                throw new \LogicException('Submitted expense items are immutable.');
            }
        });
        static::deleting(function (self $item): void {
            if ($item->expenseRequest->status !== ExpenseRequestStatus::Draft) {
                throw new \LogicException('Submitted expense items cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<ExpenseRequest, $this> */
    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class);
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
}
