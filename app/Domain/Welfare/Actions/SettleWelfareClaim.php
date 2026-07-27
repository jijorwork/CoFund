<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Services\RecalculateWelfareClaimPosition;

class SettleWelfareClaim
{
    public function __construct(private RoleAuthorization $authorization, private RecalculateWelfareClaimPosition $positions) {}

    public function handle(WelfareClaim $claim): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsSettle->value);
        $position = $this->positions->recalculate($claim);
        if ($position['recognised_liability_minor'] <= 0 || $position['outstanding_payable_minor'] !== 0) {
            throw new InvalidWelfareOperation('The welfare claim is not fully paid.');
        }
        $claim->update(['status' => WelfareClaimStatus::Settled, 'settled_at' => now()]);

        return $claim->refresh();
    }
}
