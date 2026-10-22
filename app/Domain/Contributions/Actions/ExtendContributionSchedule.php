<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Contributions\Services\GenerateContributionSchedule;

class ExtendContributionSchedule
{
    public function __construct(
        private GenerateContributionSchedule $generator,
        private ContributionScope $scope,
    ) {}

    public function handle(MemberPlanEnrolment $enrolment): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

        return $this->generator->generate($enrolment, extend: true);
    }
}
