<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

class PermissionDenied extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You are not authorized to perform this action.');
    }
}
