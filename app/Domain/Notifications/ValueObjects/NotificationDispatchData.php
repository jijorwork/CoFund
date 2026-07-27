<?php

namespace App\Domain\Notifications\ValueObjects;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;

final readonly class NotificationDispatchData
{
    /**
     * @param  list<NotificationChannel>|null  $channels
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public Organisation $organisation,
        public ?Branch $branch,
        public string $eventKey,
        public string $sourceType,
        public string $sourceUuid,
        public User $recipient,
        public string $title,
        public string $body,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public ?array $channels = null,
        public array $context = [],
    ) {}
}
