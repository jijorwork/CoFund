<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property NotificationChannel $channel
 * @property Carbon|null $activated_at
 * @property Carbon|null $retired_at
 */
#[Fillable(['public_id', 'organisation_id', 'template_key', 'event_key', 'channel', 'locale', 'name', 'subject_template', 'title_template', 'body_template', 'action_label_template', 'action_url_template', 'version', 'status', 'is_system_default', 'created_by_user_id', 'approved_by_user_id', 'activated_at', 'retired_at'])]
class NotificationTemplate extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            if ($template->getRawOriginal('status') === 'active') {
                $allowed = ['status', 'retired_at', 'updated_at'];
                if (array_diff(array_keys($template->getDirty()), $allowed) !== []) {
                    throw new \LogicException('Active notification templates are immutable.');
                }
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return HasMany<NotificationDispatch, $this> */
    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class, 'template_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'is_system_default' => 'boolean',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
