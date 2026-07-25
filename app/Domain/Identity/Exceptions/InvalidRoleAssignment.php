<?php

namespace App\Domain\Identity\Exceptions;

use DomainException;

class InvalidRoleAssignment extends DomainException
{
    public function __construct(string $message = 'The role assignment is invalid.')
    {
        parent::__construct($message);
    }
}
