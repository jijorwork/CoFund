<?php

namespace App\Domain\Collections\Exceptions;

use RuntimeException;

class InvalidContributionPayment extends RuntimeException
{
    public function __construct(string $message = 'The contribution payment operation is invalid.')
    {
        parent::__construct($message);
    }
}
