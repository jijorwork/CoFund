<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Accounting\Models\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property AccountingPeriodStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $closed_at
 * @property Carbon|null $reopened_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'name', 'starts_on', 'ends_on', 'status',
    'closed_at', 'closed_by_user_id', 'reopened_at', 'reopened_by_user_id',
    'reopening_reason',
])]
class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open'];

    protected static function newFactory(): AccountingPeriodFactory
    {
        return AccountingPeriodFactory::new();
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
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AccountingPeriodStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
