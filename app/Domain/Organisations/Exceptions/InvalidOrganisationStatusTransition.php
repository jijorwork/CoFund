<?php

namespace App\Domain\Organisations\Exceptions;

use DomainException;

class InvalidOrganisationStatusTransition extends DomainException
{
    public function __construct(string $message = 'The organisation status transition is invalid.')
    {
        parent::__construct($message);
    }
}
