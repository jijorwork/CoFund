<?php

namespace App\Domain\Organisations\Tenancy;

use App\Domain\Identity\Exceptions\InvalidRoleContext;
use App\Domain\Identity\Exceptions\RoleContextMissing;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\TenantContextMissing;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;

class TenantContext
{
    private ?Organisation $organisation = null;

    private ?Branch $branch = null;

    private ?OrganisationUserRole $roleAssignment = null;

    public function setOrganisation(Organisation $organisation): void
    {
        $this->organisation = $organisation;
        $this->branch = null;
        $this->roleAssignment = null;
    }

    public function organisation(): Organisation
    {
        return $this->organisation ?? throw new TenantContextMissing;
    }

    public function hasOrganisation(): bool
    {
        return $this->organisation !== null;
    }

    public function setBranch(?Branch $branch): void
    {
        if ($branch !== null && $branch->organisation_id !== $this->organisation()->getKey()) {
            throw new BranchOutsideTenant;
        }

        $this->branch = $branch;
        $this->roleAssignment = null;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function setRoleAssignment(OrganisationUserRole $assignment): void
    {
        if (
            $assignment->organisation_id !== $this->organisation()->getKey()
            || ! $assignment->isCurrentlyValid()
            || ($assignment->branch_id !== null && $this->branch?->getKey() !== $assignment->branch_id)
        ) {
            throw new InvalidRoleContext;
        }

        $this->roleAssignment = $assignment;
    }

    public function roleAssignment(): OrganisationUserRole
    {
        return $this->roleAssignment ?? throw new RoleContextMissing;
    }

    public function role(): Role
    {
        return $this->roleAssignment()->role;
    }

    public function hasRole(): bool
    {
        return $this->roleAssignment !== null;
    }

    public function clearRole(): void
    {
        $this->roleAssignment = null;
    }

    public function clear(): void
    {
        $this->organisation = null;
        $this->branch = null;
        $this->roleAssignment = null;
    }
}
