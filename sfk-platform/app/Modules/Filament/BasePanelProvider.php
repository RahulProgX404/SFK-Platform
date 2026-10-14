<?php

declare(strict_types=1);

namespace App\Modules\Filament;

use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

/**
 * Common configuration for every Filament panel in the platform (ERP panel and extension panels):
 * standard middleware stack, login page, shared users/guard, colours and branding.
 *
 * Look & feel follows kintone, which the staff already know: light theme only, kintone blue actions,
 * flat bordered boxes and a Meiryo-first font (core::filament.kintone-theme).
 *
 * Panels differ only in id, path/domain, brand label and which components they load.
 * Access to each panel is checked in User::canAccessPanel() via the "panel.<id>.access" permission.
 */
abstract class BasePanelProvider extends PanelProvider
{
    /** kintone's action blue; also used by the portal and the charts. */
    public const KINTONE_BLUE = '#3498db';

    public const KINTONE_RED = '#e74c3c';

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
                'primary' => Color::hex(self::KINTONE_BLUE),
                'danger' => Color::hex(self::KINTONE_RED),
                'gray' => Color::Gray,
            ])
            ->darkMode(false)
            // System Japanese fonts (no web-font download); the full stack is set by the theme view.
            ->font('Meiryo', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('core::filament.kintone-theme')->render())
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
                EnsurePasswordIsChanged::class,
            ])
            ->userMenuItems([
                Action::make('portal')
                    ->label('ダッシュボード選択')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (): string => route('core.portal')),
                Action::make('change-password')
                    ->label('パスワード変更')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn (): string => route('core.password.edit')),
            ]);
    }
}
