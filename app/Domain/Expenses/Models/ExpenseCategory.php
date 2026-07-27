<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Enums\ExpenseCategoryStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Expenses\Models\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property ExpenseCategoryStatus $status */
#[Fillable(['public_id', 'organisation_id', 'parent_id', 'code', 'name', 'description', 'default_ledger_account_id', 'requires_receipt', 'requires_preapproval', 'requires_secondary_approval', 'maximum_without_secondary_approval_minor', 'allowed_payment_methods', 'status', 'created_by_user_id'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active', 'requires_receipt' => false, 'requires_preapproval' => true, 'requires_secondary_approval' => false];

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

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function defaultLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'default_ledger_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ExpenseCategoryStatus::class, 'requires_receipt' => 'boolean', 'requires_preapproval' => 'boolean', 'requires_secondary_approval' => 'boolean', 'allowed_payment_methods' => 'array'];
    }
}
