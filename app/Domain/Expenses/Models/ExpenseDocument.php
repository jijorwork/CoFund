<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['public_id', 'organisation_id', 'documentable_type', 'documentable_id', 'document_type', 'original_name', 'stored_name', 'disk', 'path', 'mime_type', 'size_bytes', 'checksum', 'uploaded_by_user_id'])]
class ExpenseDocument extends Model
{
    use HasUuids;

    protected $hidden = ['disk', 'path', 'stored_name'];

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
}
