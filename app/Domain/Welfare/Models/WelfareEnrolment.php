<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Enums\WelfareEnrolmentStatus;
use Database\Factories\Domain\Welfare\Models\WelfareEnrolmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property WelfareEnrolmentStatus $status
 * @property Carbon $coverage_start_date
 * @property Carbon|null $waiting_period_end_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'member_id', 'welfare_product_id', 'enrolment_number', 'enrolled_at', 'coverage_start_date', 'waiting_period_end_date', 'status', 'active_enrolment_key', 'approved_at', 'activated_at', 'suspended_at', 'suspension_reason', 'terminated_at', 'termination_reason', 'created_by_user_id', 'approved_by_user_id'])]
class WelfareEnrolment extends Model
{
    /** @use HasFactory<WelfareEnrolmentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare enrolments cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation,$this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Member,$this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<WelfareProduct,$this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WelfareProduct::class, 'welfare_product_id');
    }

    /** @return HasMany<WelfareClaim,$this> */
    public function claims(): HasMany
    {
        return $this->hasMany(WelfareClaim::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareEnrolmentStatus::class, 'enrolled_at' => 'date', 'coverage_start_date' => 'date', 'waiting_period_end_date' => 'date', 'approved_at' => 'datetime', 'activated_at' => 'datetime', 'suspended_at' => 'datetime', 'terminated_at' => 'datetime'];
    }
}
