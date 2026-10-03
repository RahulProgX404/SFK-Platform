<?php

declare(strict_types=1);

namespace App\Modules\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Common configuration for every Filament panel in the platform (ERP panel and extension panels):
 * standard middleware stack, login page, shared users/guard, colours and branding.
 *
 * Panels differ only in id, path/domain, brand label and which components they load.
 * Access to each panel is checked in User::canAccessPanel() via the "panel.<id>.access" permission.
 */
abstract class BasePanelProvider extends PanelProvider
{
    protected function configureBase(Panel $panel, string $id, string $path, ?string $domain = null, ?string $brand = null): Panel
    {
        $csrf = class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : VerifyCsrfToken::class;

        return $panel
            ->id($id)
            ->path($path)
            ->domain($domain ?: null)
            ->login()
            ->authGuard('web')
            ->brandName($brand ?? (string) config('app.name'))
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                $csrf,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
