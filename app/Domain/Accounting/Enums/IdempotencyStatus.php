<?php

namespace App\Domain\Accounting\Enums;

enum IdempotencyStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
