<?php

namespace App\Domain\Loans\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $disk
 * @property string $path
 * @property string $original_name
 */
#[Fillable(['public_id', 'organisation_id', 'loan_application_id', 'documentable_type', 'documentable_id', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'uploaded_by_user_id'])]
class LoanDocument extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Loan documents are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Loan documents cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<LoanApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
