<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareEnrolment;

class ReinstateWelfareEnrolment
{
    public function __construct(private ManageWelfareEnrolment $manager) {}

    public function handle(WelfareEnrolment $enrolment): WelfareEnrolment
    {
        return $this->manager->reinstate($enrolment);
    }
}
