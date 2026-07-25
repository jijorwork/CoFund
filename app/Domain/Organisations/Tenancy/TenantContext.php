<?php

namespace App\Domain\Organisations\Tenancy;

use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\TenantContextMissing;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;

class TenantContext
{
    private ?Organisation $organisation = null;

    private ?Branch $branch = null;

    public function setOrganisation(Organisation $organisation): void
    {
        $this->organisation = $organisation;
        $this->branch = null;
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
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function clear(): void
    {
        $this->organisation = null;
        $this->branch = null;
    }
}
