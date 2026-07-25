<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindContributionEnrolmentByUuid
{
    public function __construct(private TenantContext $context, private ContributionScope $scope) {}

    public function handle(string $uuid): ?MemberPlanEnrolment
    {
        $query = MemberPlanEnrolment::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['contributionPlan.organisation', 'member', 'branch', 'group', 'obligations'])
            ->where('public_id', $uuid);
        $this->scope->applyEnrolmentAccess($query);

        return $query->first();
    }
}
