<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Models\User;
use Database\Factories\Domain\Organisations\Models\OrganisationStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $organisation_id
 * @property OrganisationStatus $from_status
 * @property OrganisationStatus $to_status
 * @property string|null $reason
 * @property int $changed_by_user_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
#[Fillable([
    'public_id',
    'organisation_id',
    'from_status',
    'to_status',
    'reason',
    'changed_by_user_id',
    'metadata',
])]
class OrganisationStatusHistory extends Model
{
    /** @use HasFactory<OrganisationStatusHistoryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function newFactory(): OrganisationStatusHistoryFactory
    {
        return OrganisationStatusHistoryFactory::new();
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

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn (): bool => throw new LogicException('Organisation status history is immutable.'));
        static::deleting(static fn (): bool => throw new LogicException('Organisation status history is immutable.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => OrganisationStatus::class,
            'to_status' => OrganisationStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
