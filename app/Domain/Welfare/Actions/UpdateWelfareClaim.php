<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Services\CalculateWelfareBenefit;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;

class UpdateWelfareClaim
{
    public function __construct(private RoleAuthorization $authorization, private EvaluateWelfareEligibility $eligibility, private CalculateWelfareBenefit $benefits) {}

    /** @param array<string,mixed> $attributes */
    public function handle(WelfareClaim $claim, array $attributes): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsUpdate->value);
        if ($claim->status !== WelfareClaimStatus::Draft) {
            throw new InvalidWelfareOperation('Only draft welfare claims may be updated.');
        }
        $claim->update($attributes);
        $eligibility = $this->eligibility->evaluate($claim);
        $benefit = $this->benefits->calculate($claim, $attributes['declared_cost_minor'] ?? null);
        $claim->update(['eligibility_status' => $eligibility['eligibility_status'], 'eligibility_snapshot' => $eligibility, 'eligible_benefit_minor' => min($benefit['eligible_benefit_minor'], $eligibility['maximum_eligible_benefit_minor']), 'benefit_snapshot' => $benefit]);

        return $claim->refresh();
    }
}
