<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Identity\Models\OrganisationUserRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $organisation_id
 * @property int|null $branch_id
 * @property int $user_id
 * @property int $role_id
 * @property RoleAssignmentStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $assigned_by_user_id
 * @property Carbon $assigned_at
 * @property int|null $revoked_by_user_id
 * @property Carbon|null $revoked_at
 * @property string|null $revocation_reason
 * @property string|null $active_scope_key
 */
#[Fillable([
    'public_id',
    'organisation_id',
    'branch_id',
    'user_id',
    'role_id',
    'status',
    'starts_at',
    'ends_at',
    'assigned_by_user_id',
    'assigned_at',
    'revoked_by_user_id',
    'revoked_at',
    'revocation_reason',
    'active_scope_key',
])]
class OrganisationUserRole extends Model
{
    /** @use HasFactory<OrganisationUserRoleFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function newFactory(): OrganisationUserRoleFactory
    {
        return OrganisationUserRoleFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<OrganisationUserRole>  $query
     * @return Builder<OrganisationUserRole>
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query
            ->where('status', RoleAssignmentStatus::Active)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()));
    }

    public function isCurrentlyValid(): bool
    {
        $now = now();

        return $this->status === RoleAssignmentStatus::Active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($now))
            && ($this->ends_at === null || $this->ends_at->greaterThan($now));
    }

    public static function activeScopeKey(
        Organisation $organisation,
        User $user,
        Role $role,
        ?Branch $branch,
    ): string {
        return implode(':', [
            $organisation->getKey(),
            $user->getKey(),
            $role->getKey(),
            $branch?->getKey() ?? '*',
        ]);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoleAssignmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
