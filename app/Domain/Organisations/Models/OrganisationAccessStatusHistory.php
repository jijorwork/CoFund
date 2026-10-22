<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_id',
    'organisation_id',
    'organisation_user_id',
    'from_status',
    'to_status',
    'reason',
    'effective_at',
    'changed_by_user_id',
    'acting_role_id',
    'metadata',
])]
class OrganisationAccessStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Organisation access history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Organisation access history is append-only.'));
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

    /** @return BelongsTo<OrganisationUser, $this> */
    public function organisationAccess(): BelongsTo
    {
        return $this->belongsTo(OrganisationUser::class, 'organisation_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => OrganisationAccessStatus::class,
            'to_status' => OrganisationAccessStatus::class,
            'effective_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
