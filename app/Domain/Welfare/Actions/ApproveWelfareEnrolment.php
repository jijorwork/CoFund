<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Models\User;

class ApproveWelfareEnrolment
{
    public function __construct(private ManageWelfareEnrolment $manager) {}

    public function handle(User $actor, WelfareEnrolment $enrolment): WelfareEnrolment
    {
        return $this->manager->approve($actor, $enrolment);
    }
}
