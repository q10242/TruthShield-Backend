<?php

namespace App\Providers\Filament;

use App\Http\Middleware\LocalFilamentAdmin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->brandName('TruthShield Command')
            ->colors([
                'primary' => Color::Cyan,
                'gray' => Color::Zinc,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        :root {
                            --truthshield-bg: #05070a;
                            --truthshield-panel: rgba(24, 24, 27, .88);
                            --truthshield-line: rgba(103, 232, 249, .18);
                        }
                        .fi-body {
                            background:
                                radial-gradient(circle at top left, rgba(6, 182, 212, .14), transparent 32rem),
                                linear-gradient(135deg, #05070a 0%, #09090b 42%, #111827 100%) !important;
                        }
                        .fi-sidebar,
                        .fi-topbar nav,
                        .fi-simple-main-ctn,
                        .fi-ta-ctn,
                        .fi-section,
                        .fi-wi {
                            border-color: var(--truthshield-line) !important;
                            background-color: var(--truthshield-panel) !important;
                            backdrop-filter: blur(18px);
                        }
                        .fi-logo {
                            letter-spacing: .02em;
                            color: #ecfeff !important;
                        }
                        .fi-sidebar-header {
                            border-bottom: 1px solid var(--truthshield-line);
                        }
                        .fi-sidebar-item-active a,
                        .fi-sidebar-item a:hover {
                            background: rgba(6, 182, 212, .12) !important;
                            color: #cffafe !important;
                        }
                        .fi-btn {
                            border-radius: .5rem !important;
                        }
                        .fi-input-wrp,
                        .fi-fo-field-wrp input {
                            background: rgba(9, 9, 11, .74) !important;
                        }
                        .fi-simple-header-heading::after {
                            content: 'Reputation, evidence, and anti-abuse operations';
                            display: block;
                            margin-top: .5rem;
                            font-size: .8rem;
                            font-weight: 500;
                            color: #67e8f9;
                        }
                    </style>
                HTML),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
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
                LocalFilamentAdmin::class,
            ]);
    }
}
