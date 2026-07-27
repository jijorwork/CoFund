<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;

class FailWelfareClaimVerification
{
    public function __construct(private ManageWelfareClaimWorkflow $workflow) {}

    public function handle(User $actor, WelfareClaim $claim, string $reason): WelfareClaim
    {
        return $this->workflow->verify($actor, $claim, false, $reason);
    }
}
