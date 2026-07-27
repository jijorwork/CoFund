<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Expenses\Enums\ExpenseApprovalStatus;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Expenses\Models\ExpenseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ExpenseRequestStatus $status
 * @property ExpenseApprovalStatus $approval_status
 * @property array{review_required: bool, primary_required: bool, secondary_required: bool, distinct_actors: bool}|null $approval_requirements
 * @property Carbon|null $needed_by_date
 * @property Carbon|null $expense_date
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'expense_number', 'requester_user_id', 'requested_for_user_id', 'beneficiary_name', 'beneficiary_type', 'expense_category_id', 'title', 'purpose', 'requested_amount_minor', 'approved_amount_minor', 'paid_amount_minor', 'outstanding_amount_minor', 'currency_code', 'needed_by_date', 'expense_date', 'payment_method_preference', 'requested_source_wallet_id', 'status', 'approval_status', 'approval_requirements', 'submitted_at', 'reviewed_at', 'reviewed_by_user_id', 'approved_at', 'approved_by_user_id', 'rejected_at', 'rejected_by_user_id', 'rejection_reason', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'completed_at', 'completed_by_user_id', 'notes'])]
class ExpenseRequest extends Model
{
    /** @use HasFactory<ExpenseRequestFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'approval_status' => 'not_submitted', 'paid_amount_minor' => 0, 'outstanding_amount_minor' => 0];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if ($request->getOriginal('status') !== ExpenseRequestStatus::Draft->value) {
                $allowed = [
                    'status', 'approval_status', 'approval_requirements',
                    'approved_amount_minor', 'paid_amount_minor', 'outstanding_amount_minor',
                    'submitted_at', 'reviewed_at', 'reviewed_by_user_id',
                    'approved_at', 'approved_by_user_id', 'rejected_at',
                    'rejected_by_user_id', 'rejection_reason', 'cancelled_at',
                    'cancelled_by_user_id', 'cancellation_reason', 'completed_at',
                    'completed_by_user_id', 'updated_at',
                ];
                if (array_diff(array_keys($request->getDirty()), $allowed) !== []) {
                    throw new \LogicException('Submitted expense requests cannot be edited.');
                }
            }
        });
        static::deleting(fn () => throw new \LogicException('Expense requests cannot be deleted.'));
    }

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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function requestedSourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'requested_source_wallet_id');
    }

    /** @return HasMany<ExpenseRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseRequestItem::class)->orderBy('sequence_number');
    }

    /** @return HasMany<ExpenseApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class)->orderBy('sequence_number');
    }

    /** @return HasMany<ExpensePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    /** @return HasMany<ExpenseDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class, 'documentable_id')->where('documentable_type', 'expense_request');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ExpenseRequestStatus::class, 'approval_status' => ExpenseApprovalStatus::class, 'approval_requirements' => 'array', 'needed_by_date' => 'date', 'expense_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
