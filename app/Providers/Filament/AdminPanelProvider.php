<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\OrganisationOverview;
use App\Http\Middleware\EnsureFilamentContext;
use App\Http\Middleware\EnsureOrganisationSubscription;
use App\Http\Middleware\ResolveRoleContext;
use App\Http\Middleware\ResolveTenantContext;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('CoFund Administration')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->globalSearchResourceOptIn()
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament.components.active-context'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                OrganisationOverview::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Overview'),
                NavigationGroup::make('Organisation'),
                NavigationGroup::make('Members'),
                NavigationGroup::make('Contributions'),
                NavigationGroup::make('Collections'),
                NavigationGroup::make('Accounting'),
                NavigationGroup::make('Expenses'),
                NavigationGroup::make('Loans'),
                NavigationGroup::make('Welfare'),
                NavigationGroup::make('Withdrawals'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Notifications'),
                NavigationGroup::make('Audit'),
                NavigationGroup::make('Settings'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureFilamentContext::class,
                ResolveTenantContext::class,
                EnsureOrganisationSubscription::class,
                ResolveRoleContext::class,
            ], isPersistent: true);
    }
}
