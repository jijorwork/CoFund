<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Contributions\Services\ValidateContributionPlanConfiguration;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use Illuminate\Support\Arr;

class UpdateContributionPlan
{
    private const CRITICAL = [
        'frequency', 'amount_type', 'fixed_amount_minor', 'minimum_amount_minor',
        'maximum_amount_minor', 'collection_day_of_week',
        'collection_day_of_month', 'currency_code',
    ];

    public function __construct(
        private ContributionScope $scope,
        private ValidateContributionPlanConfiguration $validator,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ContributionPlan $plan, array $attributes, ?Branch $branch, ?Group $group): ContributionPlan
    {
        $this->scope->assertAccessiblePlan($plan);
        $this->scope->assertPlanScope($branch, $group);

        if (in_array(
            $plan->status,
            [ContributionPlanStatus::Closed, ContributionPlanStatus::Archived],
            true,
        )) {
            throw new InvalidContributionOperation('Closed contribution plans are immutable.');
        }

        if (
            $plan->enrolments()->whereIn('status', ['pending', 'active', 'suspended'])->exists()
            && Arr::hasAny($attributes, self::CRITICAL)
        ) {
            throw new InvalidContributionOperation('Schedule-critical fields are locked after enrolment.');
        }

        $configuration = [
            ...$attributes,
            'branch_id' => $branch?->getKey(),
            'group_id' => $group?->getKey(),
        ];
        $candidate = $plan->replicate()->fill($configuration);
        $this->validator->assertValid($candidate);
        $plan->update($configuration);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
