<?php

namespace App\Domain\Notifications\ValueObjects;

final readonly class SmsMessage
{
    public function __construct(public string $destination, public string $body) {}
}
