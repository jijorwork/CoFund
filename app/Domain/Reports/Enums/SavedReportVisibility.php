<?php

namespace App\Domain\Reports\Enums;

enum SavedReportVisibility: string
{
    case Private = 'private';
    case Role = 'role';
    case Organisation = 'organisation';
}
