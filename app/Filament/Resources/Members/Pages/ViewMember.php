<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->load([
            'createdBy',
            'statusHistories.changedBy',
            'statusHistories.actingRole',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...MemberResource::accountLinkActions(),
            ...MemberResource::lifecycleActions(),
        ];
    }
}
