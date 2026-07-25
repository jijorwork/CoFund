<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\BranchStatus;
use Database\Factories\Domain\Organisations\Models\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id',
    'organisation_id',
    'name',
    'code',
    'phone',
    'email',
    'address',
    'status',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeForOrganisation(Builder $query, Organisation $organisation): Builder
    {
        return $query->whereBelongsTo($organisation);
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BranchStatus::Active);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
        ];
    }
}
