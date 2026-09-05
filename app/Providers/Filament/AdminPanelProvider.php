<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnsureActiveOffice;
use App\Http\Middleware\EnsureFeatureModuleEnabled;
use App\Http\Middleware\RequireApplicationAssignment;
use App\Services\AccessContextService;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Procurement',
                'Approvals',
                'Master Data',
                'Umrah Operations',
                'Organization & Finance',
                'Approval',
                'Settings',
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): View => view('filament.office-context', [
                    'context' => app(AccessContextService::class),
                ]),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->resources([
                config('filament-logger.activity_resource'),
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Settings')
                    ->navigationLabel('Roles')
                    ->navigationSort(30),
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
            ->persistentMiddleware([
                RequireApplicationAssignment::class,
                EnsureActiveOffice::class,
            ])
            ->authMiddleware([
                RequireApplicationAssignment::class,
                EnsureActiveOffice::class,
                Authenticate::class,
                EnsureFeatureModuleEnabled::class,
            ])
            ->spa()
            ->spaUrlExceptions([
                '*/exports/*/download',
                '*/imports/*/failed-rows/download',
                '*/auth/keycloak/*',
            ]);
    }
}
