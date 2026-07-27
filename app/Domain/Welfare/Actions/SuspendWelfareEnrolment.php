<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Models\WelfareEnrolment;

class SuspendWelfareEnrolment
{
    public function __construct(private ManageWelfareEnrolment $manager) {}

    public function handle(WelfareEnrolment $enrolment, string $reason): WelfareEnrolment
    {
        return $this->manager->suspend($enrolment, $reason);
    }
}
