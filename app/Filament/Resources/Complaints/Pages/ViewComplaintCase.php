<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\ComplaintCaseResource;
use Filament\Resources\Pages\ViewRecord;

class ViewComplaintCase extends ViewRecord
{
    protected static string $resource = ComplaintCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComplaintCaseResource::respondAction(),
            ComplaintCaseResource::assignAction(),
            ComplaintCaseResource::resolveAction(),
            ComplaintCaseResource::closeAction(),
            ComplaintCaseResource::reopenAction(),
        ];
    }
}
