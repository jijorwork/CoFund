<?php

namespace App\Domain\Complaints\Models;

use App\Domain\Complaints\Enums\ComplaintStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Complaints\Models\ComplaintCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property ComplaintStatus $status */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'member_id', 'reported_by_user_id',
    'assigned_to_user_id', 'subject', 'category', 'status', 'resolved_at',
    'resolved_by_user_id', 'resolution_summary', 'closed_at', 'closed_by_user_id',
])]
class ComplaintCase extends Model
{
    /** @use HasFactory<ComplaintCaseFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open'];

    protected static function newFactory(): ComplaintCaseFactory
    {
        return ComplaintCaseFactory::new();
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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return HasMany<ComplaintMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
