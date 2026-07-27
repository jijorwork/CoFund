<?php

namespace App\Domain\Withdrawals\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Enums\SavingsHoldStatus;
use Database\Factories\Domain\Withdrawals\Models\SavingsHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SavingsHoldStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'member_id', 'wallet_id', 'hold_number', 'hold_type', 'source_type', 'source_uuid', 'amount_minor', 'currency_code', 'status', 'reason', 'notes', 'starts_at', 'expires_at', 'created_by_user_id', 'approved_by_user_id', 'released_at', 'released_by_user_id', 'release_reason'])]
class SavingsHold extends Model
{
    /** @use HasFactory<SavingsHoldFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Savings holds cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => SavingsHoldStatus::class, 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
