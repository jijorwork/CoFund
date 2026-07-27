<?php

namespace App\Domain\Notifications\ValueObjects;

final readonly class ProviderDeliveryResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerReference = null,
        public ?string $responseCode = null,
        public ?string $failureCode = null,
        public ?string $failureReason = null,
        public bool $retryable = false,
    ) {}

    public static function unavailable(string $channel): self
    {
        return new self(false, failureCode: 'provider_unavailable', failureReason: ucfirst($channel).' delivery is not configured.');
    }
}
