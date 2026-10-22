<?php

namespace App\Domain\Organisations\Exceptions;

use DomainException;
use Throwable;

class InvalidOrganisationAccess extends DomainException
{
    public function __construct(
        string $message = 'The organisation access request is invalid.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
