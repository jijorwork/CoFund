<?php

namespace App\Domain\Approvals\Rules;

use App\Domain\Identity\Auditing\ActingContext;
use DomainException;

final class EnsureDifferentActor
{
    public function handle(ActingContext $actingContext, int $requesterUserId): void
    {
        if ($actingContext->userId === $requesterUserId) {
            throw new DomainException('A requester cannot approve their own request.');
        }
    }
}
