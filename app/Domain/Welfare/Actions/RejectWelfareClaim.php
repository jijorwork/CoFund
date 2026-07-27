<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;

class RejectWelfareClaim
{
    public function __construct(private ManageWelfareClaimWorkflow $workflow) {}

    public function handle(User $actor, WelfareClaim $claim, string $reason): WelfareClaim
    {
        return $this->workflow->reject($actor, $claim, $reason);
    }
}
