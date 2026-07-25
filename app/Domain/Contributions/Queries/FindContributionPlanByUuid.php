<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindContributionPlanByUuid
{
    public function __construct(private TenantContext $context, private ContributionScope $scope) {}

    public function handle(string $uuid): ?ContributionPlan
    {
        $query = ContributionPlan::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'group'])
            ->where('public_id', $uuid);
        $this->scope->applyPlanAccess($query);

        return $query->first();
    }
}
