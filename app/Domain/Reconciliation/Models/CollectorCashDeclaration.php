<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CashDeclarationStatus;
use App\Models\User;
use Database\Factories\Domain\Reconciliation\Models\CollectorCashDeclarationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CashDeclarationStatus $status
 * @property Carbon $declared_at
 * @property list<array{denomination_minor: int, quantity: int}>|null $denomination_breakdown
 */
#[Fillable([
    'public_id', 'organisation_id', 'collector_collection_session_id',
    'declaration_number', 'declared_cash_minor', 'currency_code',
    'denomination_breakdown', 'declared_at', 'declared_by_user_id', 'notes',
    'status', 'superseded_by_id', 'active_session_key',
])]
class CollectorCashDeclaration extends Model
{
    /** @use HasFactory<CollectorCashDeclarationFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if (array_diff(array_keys($declaration->getDirty()), ['status', 'superseded_by_id', 'active_session_key', 'updated_at']) !== []) {
                throw new \LogicException('Cash declarations are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Cash declarations cannot be deleted.'));
    }

    protected static function newFactory(): CollectorCashDeclarationFactory
    {
        return CollectorCashDeclarationFactory::new();
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

    /** @return BelongsTo<CollectorCollectionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CollectorCollectionSession::class, 'collector_collection_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_user_id');
    }

    /** @return BelongsTo<CollectorCashDeclaration, $this> */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CashDeclarationStatus::class,
            'denomination_breakdown' => 'array',
            'declared_at' => 'datetime',
        ];
    }
}
