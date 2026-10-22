<?php

namespace App\Filament\Widgets;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Services\BuildOrganisationDashboard;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use App\Filament\Support\FormatsMoney;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrganisationOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Organisation overview';

    protected ?string $description = 'Current month, using the active organisation and branch context.';

    public static function canView(): bool
    {
        return app(RoleAuthorization::class)->can(
            FoundationPermission::FilamentDashboardView->value,
        );
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $context = app(TenantContext::class);
        $authorization = app(RoleAuthorization::class);
        $organisation = $context->organisation();
        $data = app(BuildOrganisationDashboard::class)->build(
            ReportDateRange::from($organisation),
            $context->branch(),
        );
        $currency = $organisation->currency_code;
        $stats = [];

        if ($authorization->can(FoundationPermission::MembersView->value)) {
            $stats[] = Stat::make('Active members', $data['membership']['active_members'])
                ->icon('heroicon-o-user-group');
        }

        if ($authorization->can(FoundationPermission::ContributionPaymentsView->value)) {
            $stats[] = Stat::make(
                'Contributions collected',
                FormatsMoney::format($data['contributions']['collected_minor'], $currency),
            )->icon('heroicon-o-banknotes');
        }

        if ($authorization->can(FoundationPermission::LoansView->value)) {
            $stats[] = Stat::make(
                'Outstanding loan principal',
                FormatsMoney::format($data['loans']['outstanding_principal_minor'], $currency),
            )->icon('heroicon-o-building-library');
        }

        if ($authorization->can(FoundationPermission::WithdrawalRequestsView->value)) {
            $stats[] = Stat::make(
                'Approved withdrawals awaiting payout',
                FormatsMoney::format($data['withdrawals']['approved_awaiting_payout_minor'], $currency),
            )->icon('heroicon-o-arrow-up-tray');
        }

        return $stats;
    }
}
