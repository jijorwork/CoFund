<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApprovalDecisionOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'public_id', 'organisation_id', 'approval_request_id', 'step_number',
    'outcome', 'decided_by_user_id', 'acting_role_id', 'role_assignment_id',
    'reason',
])]
class ApprovalDecision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Approval decisions are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Approval decisions are immutable.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['outcome' => ApprovalDecisionOutcome::class, 'created_at' => 'datetime'];
    }
}
