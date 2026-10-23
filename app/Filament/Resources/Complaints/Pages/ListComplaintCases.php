<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\ComplaintCaseResource;
use Filament\Resources\Pages\ListRecords;

class ListComplaintCases extends ListRecords
{
    protected static string $resource = ComplaintCaseResource::class;
}
