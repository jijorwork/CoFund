<?php

namespace App\Domain\Welfare\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['disk', 'path'])]
#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'verification_status', 'uploaded_by_user_id', 'verified_by_user_id', 'verified_at'])]
class WelfareDocument extends Model
{
    use HasUuids;

    protected $attributes = ['verification_status' => 'pending'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare documents cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<WelfareClaim,$this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(WelfareClaim::class, 'welfare_claim_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
