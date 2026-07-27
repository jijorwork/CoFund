<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;
use Carbon\CarbonImmutable;

class SecondaryApproveWelfareClaim
{
    public function __construct(private ApproveWelfareClaim $approve) {}

    public function handle(User $actor, WelfareClaim $claim, int $amountMinor, CarbonImmutable $date, string $idempotencyKey, ?string $reason = null): WelfareClaim
    {
        return $this->approve->handle($actor, $claim, $amountMinor, 'secondary', $date, $idempotencyKey, $reason);
    }
}
