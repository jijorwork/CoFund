<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\GenerateContributionSchedule;

class GenerateInitialContributionSchedule
{
    public function __construct(private GenerateContributionSchedule $generator) {}

    public function handle(MemberPlanEnrolment $enrolment): MemberPlanEnrolment
    {
        return $this->generator->generate($enrolment);
    }
}
