<?php

namespace App\Domain\Identity\Exceptions;

use LogicException;

class RoleContextMissing extends LogicException
{
    public function __construct()
    {
        parent::__construct('Active role context has not been established.');
    }
}
