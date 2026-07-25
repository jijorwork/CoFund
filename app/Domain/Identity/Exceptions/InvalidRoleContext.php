<?php

namespace App\Domain\Identity\Exceptions;

use DomainException;

class InvalidRoleContext extends DomainException
{
    public function __construct()
    {
        parent::__construct('The selected role context is unavailable.');
    }
}
