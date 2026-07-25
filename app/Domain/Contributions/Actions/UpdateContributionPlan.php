<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;
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

    public function __construct(private ContributionScope $scope) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ContributionPlan $plan, array $attributes, ?Branch $branch, ?Group $group): ContributionPlan
    {
        $this->scope->assertPlanScope($branch, $group);

        if (
            $plan->enrolments()->whereIn('status', ['pending', 'active', 'suspended'])->exists()
            && Arr::hasAny($attributes, self::CRITICAL)
        ) {
            throw new InvalidContributionOperation('Schedule-critical fields are locked after enrolment.');
        }

        $plan->update([
            ...$attributes,
            'branch_id' => $branch?->getKey(),
            'group_id' => $group?->getKey(),
        ]);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
