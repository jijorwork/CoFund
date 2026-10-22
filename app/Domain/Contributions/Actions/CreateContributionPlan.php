<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Contributions\Services\ValidateContributionPlanConfiguration;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

class CreateContributionPlan
{
    public function __construct(
        private TenantContext $context,
        private ContributionScope $scope,
        private ValidateContributionPlanConfiguration $validator,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes, ?Branch $branch, ?Group $group): ContributionPlan
    {
        $this->scope->assertPlanScope($branch, $group);
        $organisation = $this->context->organisation();

        $configuration = [
            ...$attributes,
            'organisation_id' => $organisation->getKey(),
            'branch_id' => $branch?->getKey(),
            'group_id' => $group?->getKey(),
            'currency_code' => $attributes['currency_code'] ?? $organisation->currency_code,
            'status' => ContributionPlanStatus::Draft,
            'created_by_user_id' => $actor->getKey(),
        ];
        $candidate = new ContributionPlan($configuration);
        $this->validator->assertValid($candidate);

        return ContributionPlan::query()
            ->create($configuration)
            ->load(['branch', 'group']);
    }
}
