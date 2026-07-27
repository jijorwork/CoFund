<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Welfare\Enums\WelfareEventStatus;
use Database\Factories\Domain\Welfare\Models\WelfareEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WelfareEventStatus $status
 * @property Carbon $event_date
 * @property Carbon $reported_date
 */
#[Hidden(['related_person_name_encrypted'])]
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'member_id', 'welfare_event_type_id', 'event_number', 'event_date', 'reported_date', 'title', 'description', 'location', 'related_person_name_encrypted', 'related_person_type', 'relationship_to_member', 'status', 'verified_at', 'verified_by_user_id', 'verification_notes', 'created_by_user_id'])]
class WelfareEvent extends Model
{
    /** @use HasFactory<WelfareEventFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'reported'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare events cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Member,$this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Branch,$this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<WelfareEventType,$this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(WelfareEventType::class, 'welfare_event_type_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareEventStatus::class, 'event_date' => 'date', 'reported_date' => 'date', 'related_person_name_encrypted' => 'encrypted', 'verified_at' => 'datetime'];
    }
}
