<?php

namespace App\Domain\Reports\Enums;

enum ReportRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
