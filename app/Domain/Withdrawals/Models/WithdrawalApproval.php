<?php

namespace App\Domain\Withdrawals\Models;

use Database\Factories\Domain\Withdrawals\Models\WithdrawalApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'organisation_id', 'withdrawal_request_id', 'approval_level', 'decision', 'requested_at', 'decided_at', 'decided_by_user_id', 'acting_role_id', 'recommended_amount_minor', 'approved_amount_minor', 'reason', 'override_type', 'sequence_number'])]
class WithdrawalApproval extends Model
{
    /** @use HasFactory<WithdrawalApprovalFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Withdrawal approval history is append-only.'));
        static::deleting(fn (): never => throw new \LogicException('Withdrawal approvals cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
