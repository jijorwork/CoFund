<?php

namespace App\Domain\Reconciliation\Exceptions;

use RuntimeException;

class InvalidReconciliationOperation extends RuntimeException
{
    public function __construct(string $message = 'The reconciliation operation is invalid.')
    {
        parent::__construct($message);
    }
}
