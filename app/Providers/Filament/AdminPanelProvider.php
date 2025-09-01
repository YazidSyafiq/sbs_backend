<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Http\Request;
use Filament\View\PanelsRenderHook;
use Filament\Enums\ThemeMode;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->defaultThemeMode(ThemeMode::Dark)
            ->brandLogo(asset('default/images/img_logo_dark.png'))
            ->darkModeBrandLogo(asset('default/images/img_logo.png'))
            ->brandLogoHeight(function (Request $request): string {
                if ($request->route()->getName() === 'filament.admin.auth.login') {
                    return '100px';
                }

                return '50px';
            })
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.components.login-footer')->render()
            )
            ->colors([
                'primary' => '#FDB515',
                'success' => '#4ECB25',
                'slate' => '#64748B',     // Abu-abu gelap untuk Draft
                'amber' => '#F59E0B',     // Kuning keemasan untuk Requested
                'blue' => '#3B82F6',      // Biru untuk Processing
                'purple' => '#8B5CF6',    // Ungu untuk Shipped
                'green' => '#10B981',     // Hijau untuk Received
                'emerald' => '#059669',   // Hijau emerald untuk Done
                'red' => '#EF4444',       // Merah untuk Cancelled
            ])
            ->font('Poppins')
            ->favicon(asset('default/images/img_logo.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
            ]);
    }
}
