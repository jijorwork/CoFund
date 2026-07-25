<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationType;
use App\Models\User;
use Database\Factories\Domain\Organisations\Models\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id',
    'name',
    'code',
    'organisation_type',
    'phone',
    'email',
    'address',
    'logo_path',
    'currency_code',
    'timezone',
    'status',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'currency_code' => 'GHS',
        'timezone' => 'UTC',
        'status' => 'pending',
    ];

    protected static function newFactory(): OrganisationFactory
    {
        return OrganisationFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<Organisation>  $query
     * @return Builder<Organisation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganisationStatus::Active);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_users')
            ->withPivot(['id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganisationUser, $this>
     */
    public function organisationAccesses(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    /**
     * @return HasMany<OrganisationSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(OrganisationSetting::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organisation_type' => OrganisationType::class,
            'status' => OrganisationStatus::class,
        ];
    }
}
