<?php

namespace App\Domain\Notifications\ValueObjects;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationPriority;

final readonly class NotificationEventDefinition
{
    /**
     * @param  list<NotificationChannel>  $defaultChannels
     * @param  list<NotificationChannel>  $allowedChannels
     * @param  list<string>  $requiredPlaceholders
     * @param  list<string>  $sensitivePlaceholders
     */
    public function __construct(
        public string $key,
        public NotificationCategory $category,
        public string $displayName,
        public string $description,
        public array $defaultChannels,
        public array $allowedChannels,
        public bool $mandatory,
        public bool $digestible,
        public string $recipientResolver,
        public array $requiredPlaceholders,
        public array $sensitivePlaceholders = [],
        public NotificationPriority $priority = NotificationPriority::Normal,
    ) {}
}
