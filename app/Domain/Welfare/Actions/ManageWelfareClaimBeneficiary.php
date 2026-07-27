<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use Illuminate\Support\Str;

class ManageWelfareClaimBeneficiary
{
    public function __construct(private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function create(WelfareClaim $claim, array $attributes): WelfareClaimBeneficiary
    {
        $this->authorization->authorize(FoundationPermission::WelfareBeneficiariesManage->value);
        if ($claim->status !== WelfareClaimStatus::Draft) {
            throw new InvalidWelfareOperation('Beneficiaries may only be added to draft claims.');
        }
        $this->validateAllocation($claim, $attributes);
        $identifier = trim((string) ($attributes['identification_reference'] ?? ''));
        unset($attributes['identification_reference']);

        return $claim->beneficiaries()->create([...$attributes, 'organisation_id' => $claim->organisation_id, 'identification_reference_encrypted' => $identifier !== '' ? $identifier : null, 'identification_last_four' => $identifier !== '' ? Str::substr($identifier, -4) : null]);
    }

    /** @param array<string,mixed> $attributes */
    public function update(WelfareClaimBeneficiary $beneficiary, array $attributes): WelfareClaimBeneficiary
    {
        $this->authorization->authorize(FoundationPermission::WelfareBeneficiariesManage->value);
        if ($beneficiary->claim->status !== WelfareClaimStatus::Draft) {
            throw new InvalidWelfareOperation('Approved beneficiary changes require an amendment.');
        }
        $this->validateAllocation($beneficiary->claim, $attributes, $beneficiary);
        $beneficiary->update($attributes);

        return $beneficiary->refresh();
    }

    /** @param array<string,mixed> $attributes */
    private function validateAllocation(WelfareClaim $claim, array $attributes, ?WelfareClaimBeneficiary $except = null): void
    {
        $query = $claim->beneficiaries()->where('status', 'active');
        if ($except !== null) {
            $query->where('id', '!=', $except->getKey());
        }
        $basis = (int) $query->sum('allocation_basis_points') + (int) ($attributes['allocation_basis_points'] ?? 0);
        $amount = (int) $query->sum('allocated_benefit_minor') + (int) ($attributes['allocated_benefit_minor'] ?? 0);
        if ($basis > 10000 || ($claim->approved_benefit_minor !== null && $amount > $claim->approved_benefit_minor)) {
            throw new InvalidWelfareOperation('Beneficiary allocations exceed the claim limits.');
        }
    }
}
