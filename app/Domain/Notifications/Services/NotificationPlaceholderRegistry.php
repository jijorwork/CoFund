<?php

namespace App\Domain\Notifications\Services;

use InvalidArgumentException;

class NotificationPlaceholderRegistry
{
    /** @var list<string> */
    private const GLOBAL_PLACEHOLDERS = [
        'organisation.name',
        'organisation.code',
        'recipient.name',
        'recipient.first_name',
        'action.url',
        'action.label',
    ];

    public function __construct(private readonly NotificationEventRegistry $events) {}

    /** @return list<string> */
    public function allowedFor(string $eventKey): array
    {
        return array_values(array_unique([
            ...self::GLOBAL_PLACEHOLDERS,
            ...$this->events->get($eventKey)->requiredPlaceholders,
        ]));
    }

    /** @param array<string, mixed> $values */
    public function validate(string $eventKey, array $values): void
    {
        $allowed = $this->allowedFor($eventKey);
        $unknown = array_diff(array_keys($values), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported notification placeholders: '.implode(', ', $unknown));
        }

        $missing = array_diff($this->events->get($eventKey)->requiredPlaceholders, array_keys($values));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing notification placeholders: '.implode(', ', $missing));
        }
    }
}
