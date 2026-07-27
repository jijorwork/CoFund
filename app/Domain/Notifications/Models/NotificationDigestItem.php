<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['public_id', 'organisation_id', 'notification_digest_id', 'notification_dispatch_id', 'sequence_number'])]
class NotificationDigestItem extends Model
{
    public const UPDATED_AT = null;

    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<NotificationDigest, $this> */
    public function digest(): BelongsTo
    {
        return $this->belongsTo(NotificationDigest::class, 'notification_digest_id');
    }

    /** @return BelongsTo<NotificationDispatch, $this> */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(NotificationDispatch::class, 'notification_dispatch_id');
    }
}
