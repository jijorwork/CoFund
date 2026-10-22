<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Models\User;
use Database\Factories\Domain\Organisations\Models\OrganisationUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $organisation_id
 * @property int $user_id
 * @property int|null $granted_by_user_id
 * @property OrganisationAccessStatus $status
 * @property Carbon|null $joined_at
 */
#[Fillable([
    'public_id',
    'organisation_id',
    'user_id',
    'granted_by_user_id',
    'status',
    'joined_at',
])]
class OrganisationUser extends Model
{
    /** @use HasFactory<OrganisationUserFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'invited',
    ];

    protected static function newFactory(): OrganisationUserFactory
    {
        return OrganisationUserFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<OrganisationUser>  $query
     * @return Builder<OrganisationUser>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganisationAccessStatus::Active);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return HasMany<OrganisationAccessStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrganisationAccessStatusHistory::class)->latest('effective_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganisationAccessStatus::class,
            'joined_at' => 'datetime',
        ];
    }
}
