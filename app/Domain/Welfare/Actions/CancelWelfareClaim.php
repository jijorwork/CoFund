<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;

class CancelWelfareClaim
{
    public function __construct(private ManageWelfareClaimWorkflow $workflow) {}

    public function handle(User $actor, WelfareClaim $claim, string $reason): WelfareClaim
    {
        return $this->workflow->cancel($actor, $claim, $reason);
    }
}
