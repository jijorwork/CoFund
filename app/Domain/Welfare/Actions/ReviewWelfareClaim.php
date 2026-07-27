<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Models\User;

class ReviewWelfareClaim
{
    public function __construct(private ManageWelfareClaimWorkflow $workflow) {}

    public function handle(User $actor, WelfareClaim $claim, ?string $notes = null): WelfareClaim
    {
        return $this->workflow->review($actor, $claim, $notes);
    }
}
