<?php

namespace App\Domain\Notifications\ValueObjects;

final readonly class MessagingMessage
{
    public function __construct(
        public string $destination,
        public string $body,
        public ?string $providerTemplate = null,
    ) {}
}
