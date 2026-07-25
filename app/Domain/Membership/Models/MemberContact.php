<?php

namespace App\Domain\Membership\Models;

use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\MemberContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property MemberContactType $contact_type
 * @property MemberContactStatus $status
 */
#[Fillable([
    'public_id', 'organisation_id', 'member_id', 'contact_type', 'full_name',
    'relationship', 'phone', 'alternate_phone', 'email', 'address',
    'national_id_type', 'national_id_number', 'percentage_entitlement',
    'is_primary', 'is_emergency_contact', 'status', 'verified_at',
    'verified_by_user_id', 'primary_scope_key',
])]
class MemberContact extends Model
{
    /** @use HasFactory<MemberContactFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected static function newFactory(): MemberContactFactory
    {
        return MemberContactFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public static function primaryScopeKey(Member $member, MemberContactType $type): string
    {
        return $member->getKey().':'.$type->value;
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** @return MorphMany<MemberDocument, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(MemberDocument::class, 'documentable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contact_type' => MemberContactType::class,
            'status' => MemberContactStatus::class,
            'percentage_entitlement' => 'decimal:2',
            'is_primary' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'verified_at' => 'datetime',
            'national_id_number' => 'encrypted',
        ];
    }
}
