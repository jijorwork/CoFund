<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Expenses\Enums\ExpenseApprovalDecision;
use App\Domain\Expenses\Enums\ExpenseApprovalLevel;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Expenses\Models\ExpenseApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ExpenseApprovalLevel $approval_level
 * @property ExpenseApprovalDecision $decision
 * @property Carbon|null $decided_at
 */
#[Fillable(['public_id', 'organisation_id', 'expense_request_id', 'approval_level', 'decision', 'requested_at', 'decided_at', 'requested_from_role', 'decided_by_user_id', 'acting_role_id', 'approved_amount_minor', 'reason', 'sequence_number'])]
class ExpenseApproval extends Model
{
    /** @use HasFactory<ExpenseApprovalFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Expense approvals are append-only.'));
        static::deleting(fn () => throw new \LogicException('Expense approvals cannot be deleted.'));
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

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['approval_level' => ExpenseApprovalLevel::class, 'decision' => ExpenseApprovalDecision::class, 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
