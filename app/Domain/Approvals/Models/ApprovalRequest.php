<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ApprovalRequestStatus $status
 * @property list<string> $step_permissions
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'workflow_key',
    'workflow_version', 'subject_type', 'subject_public_id',
    'requested_by_user_id', 'status', 'current_step_number',
    'step_permissions', 'active_subject_key', 'submitted_at', 'completed_at',
])]
class ApprovalRequest extends Model
{
    use HasUuids;

    protected $attributes = ['status' => 'pending', 'current_step_number' => 1];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return HasMany<ApprovalDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('step_number');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStatus::class,
            'step_permissions' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
