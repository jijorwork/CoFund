<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\CollateralStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanCollateral;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Str;

class ManageLoanCollateral
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function create(LoanApplication $application, array $attributes): LoanCollateral
    {
        $this->authorization->authorize(FoundationPermission::LoanCollateralManage->value);
        if ($application->organisation_id !== $this->context->organisation()->getKey()) {
            throw new InvalidLoanOperation;
        }
        $identifier = trim((string) ($attributes['identifier'] ?? ''));
        unset($attributes['identifier']);

        return LoanCollateral::query()->create([...$attributes, 'organisation_id' => $application->organisation_id, 'loan_application_id' => $application->getKey(), 'identifier_encrypted' => $identifier !== '' ? $identifier : null, 'identifier_last_four' => $identifier !== '' ? Str::substr($identifier, -4) : null]);
    }

    /** @param array<string,mixed> $attributes */
    public function update(LoanCollateral $collateral, array $attributes): LoanCollateral
    {
        $this->authorization->authorize(FoundationPermission::LoanCollateralManage->value);
        if ($collateral->organisation_id !== $this->context->organisation()->getKey() || $collateral->status !== CollateralStatus::Proposed) {
            throw new InvalidLoanOperation('Only pending collateral may be updated.');
        }

        if (array_key_exists('identifier', $attributes)) {
            $identifier = trim((string) $attributes['identifier']);
            unset($attributes['identifier']);
            $attributes['identifier_encrypted'] = $identifier !== '' ? $identifier : null;
            $attributes['identifier_last_four'] = $identifier !== '' ? Str::substr($identifier, -4) : null;
        }

        $collateral->update($attributes);

        return $collateral->refresh();
    }

    public function review(User $actor, LoanCollateral $collateral, CollateralStatus $status, ?int $acceptedValueMinor = null, ?string $reason = null): LoanCollateral
    {
        $this->authorization->authorize(FoundationPermission::LoanCollateralManage->value);
        if (! in_array($status, [CollateralStatus::Accepted, CollateralStatus::Rejected, CollateralStatus::Released], true) || $collateral->organisation_id !== $this->context->organisation()->getKey()) {
            throw new InvalidLoanOperation;
        }
        if ($status === CollateralStatus::Accepted && ($acceptedValueMinor === null || $acceptedValueMinor <= 0)) {
            throw new InvalidLoanOperation('Accepted collateral requires an accepted value.');
        }
        if ($status === CollateralStatus::Rejected && trim((string) $reason) === '') {
            throw new InvalidLoanOperation('Collateral rejection requires a reason.');
        }
        $collateral->update(['status' => $status, 'accepted_value_minor' => $status === CollateralStatus::Accepted ? $acceptedValueMinor : $collateral->accepted_value_minor, 'reviewed_by_user_id' => $actor->getKey(), 'reviewed_at' => now(), 'released_at' => $status === CollateralStatus::Released ? now() : null, 'release_reason' => $status === CollateralStatus::Released ? $reason : null]);

        return $collateral->refresh();
    }
}
