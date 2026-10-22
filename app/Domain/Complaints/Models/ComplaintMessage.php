<?php

namespace App\Domain\Complaints\Models;

use App\Models\User;
use Database\Factories\Domain\Complaints\Models\ComplaintMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['public_id', 'organisation_id', 'complaint_case_id', 'author_user_id', 'visibility', 'body'])]
class ComplaintMessage extends Model
{
    /** @use HasFactory<ComplaintMessageFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ComplaintMessageFactory
    {
        return ComplaintMessageFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ComplaintCase, $this> */
    public function complaintCase(): BelongsTo
    {
        return $this->belongsTo(ComplaintCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Complaint messages are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Complaint messages are immutable.'));
    }
}
