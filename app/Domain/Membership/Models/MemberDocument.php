<?php

namespace App\Domain\Membership\Models;

use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Enums\MemberDocumentType;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\MemberDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property MemberDocumentType $document_type
 * @property DocumentVerificationStatus $verification_status
 * @property Carbon|null $verified_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'documentable_type', 'documentable_id',
    'document_type', 'original_name', 'stored_name', 'disk', 'path', 'mime_type',
    'size_bytes', 'checksum', 'verification_status', 'uploaded_by_user_id',
    'verified_by_user_id', 'verified_at', 'rejection_reason',
])]
class MemberDocument extends Model
{
    /** @use HasFactory<MemberDocumentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['verification_status' => 'pending'];

    protected $hidden = ['disk', 'path', 'stored_name'];

    protected static function newFactory(): MemberDocumentFactory
    {
        return MemberDocumentFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => MemberDocumentType::class,
            'verification_status' => DocumentVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }
}
