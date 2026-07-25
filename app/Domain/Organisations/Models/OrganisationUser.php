<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Models\User;
use Database\Factories\Domain\Organisations\Models\OrganisationUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organisation_id',
    'user_id',
    'status',
    'joined_at',
])]
class OrganisationUser extends Model
{
    /** @use HasFactory<OrganisationUserFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'invited',
    ];

    protected static function newFactory(): OrganisationUserFactory
    {
        return OrganisationUserFactory::new();
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
