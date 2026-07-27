<?php

namespace App\Domain\Notifications\Services;

class BuildNotificationDeduplicationKey
{
    public function build(
        string $organisationUuid,
        string $eventKey,
        string $sourceUuid,
        string $recipientType,
        string $recipientUuid,
        string $occurrenceId,
        int $version = 1,
    ): string {
        return hash('sha256', implode('|', [
            $organisationUuid,
            $eventKey,
            $sourceUuid,
            $recipientType,
            $recipientUuid,
            $occurrenceId,
            (string) $version,
        ]));
    }
}
