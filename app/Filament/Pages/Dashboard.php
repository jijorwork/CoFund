<?php

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public static function canAccess(): bool
    {
        return app(RoleAuthorization::class)->can(
            FoundationPermission::FilamentDashboardView->value,
        );
    }

    /** @return array<string, int> */
    public function getColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
