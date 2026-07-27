<?php

namespace App\Domain\Notifications\ValueObjects;

final readonly class RenderedNotification
{
    public function __construct(
        public ?string $subject,
        public ?string $title,
        public string $body,
        public ?string $actionLabel,
        public ?string $actionUrl,
    ) {}
}
