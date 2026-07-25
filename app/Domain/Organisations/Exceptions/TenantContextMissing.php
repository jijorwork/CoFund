<?php

namespace App\Domain\Organisations\Exceptions;

use LogicException;

class TenantContextMissing extends LogicException
{
    public function __construct()
    {
        parent::__construct('Tenant organisation context has not been established.');
    }
}
