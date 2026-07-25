<?php

namespace App\Domain\Organisations\Exceptions;

use DomainException;

class BranchOutsideTenant extends DomainException
{
    public function __construct()
    {
        parent::__construct('The selected branch does not belong to the active organisation.');
    }
}
