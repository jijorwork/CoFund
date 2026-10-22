<?php

namespace App\Domain\Identity\Auditing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'actor_user_id',
    'acting_role_id', 'role_assignment_id', 'event_key', 'subject_type',
    'subject_public_id', 'details',
])]
class AuditEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Audit events are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Audit events are immutable.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
