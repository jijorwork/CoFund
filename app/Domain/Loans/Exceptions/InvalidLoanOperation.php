<?php

namespace App\Domain\Loans\Exceptions;

use RuntimeException;

class InvalidLoanOperation extends RuntimeException
{
    public function __construct(string $message = 'The loan operation could not be completed.')
    {
        parent::__construct($message);
    }
}
