<?php

declare(strict_types=1);

namespace App\Modules\Filament;

use Filament\Actions\Action;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
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
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\Profile\ProfilePage;
use Modules\Core\Filament\Support\InitialAvatarProvider;
use Modules\Core\Filament\Support\ThemeAssets;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

/**
 * Common configuration for every Filament panel in the platform (ERP panel and extension panels):
 * standard middleware stack, login page, shared users/guard, colours and branding.
 *
 * Look & feel: light theme only, the brand blue, white surfaces with rounded corners and Noto Sans JP
 * (core::filament.theme with core::filament.design); no top bar.
 *
 * Panels differ only in id, path/domain, brand label and which components they load.
 * Access to each panel is checked in User::canAccessPanel() (user / 従業員カテゴリ dashboards and ユーザー種別,
 * see Modules\Core\Access\DashboardAccess). Core replaces the brand with the dashboard name, which opens a dashboard switcher.
 */
abstract class BasePanelProvider extends PanelProvider
{
    /** the primary colour (user's choice, 2026-11); also used by the portal and the charts. */
    public const BRAND_BLUE = '#05467E';

    public const BRAND_RED = '#e74c3c';

    /** the stylesheet of the self-hosted Noto Sans JP (also linked by the portal / login shell). */
    public const FONT_STYLESHEET = '/fonts/noto-sans-jp/noto-sans-jp.css?v=1';

    /** the legacy system's success notification green. */
    public const BRAND_GREEN = '#91c36c';

    protected function configureBase(Panel $panel, string $id, string $path, ?string $domain = null, ?string $brand = null): Panel
    {
        $csrf = class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : VerifyCsrfToken::class;

        return $panel
            ->id($id)
            ->path($path)
            ->domain($domain ?: null)
            // No login page per dashboard: guests go to the single /login page (Core), logout returns there.
            ->profile(ProfilePage::class, isSimple: false)
            ->authGuard('web')
            ->brandName($brand ?? (string) config('app.name'))
            ->colors([
                'primary' => Color::hex(self::BRAND_BLUE),
                'danger' => Color::hex(self::BRAND_RED),
                'success' => Color::hex(self::BRAND_GREEN),
                'info' => Color::hex(self::BRAND_BLUE),
                'gray' => Color::Gray,
            ])
            ->darkMode(false)
            // Noto Sans JP, served by this system (public/fonts/noto-sans-jp: unicode-range slices, only the characters a
            // page shows are loaded; the Content-Security-Policy allows no font host); the full stack with the system
            // Japanese fonts as fallback is set by core::filament.design (--sfk-font).
            ->font('Noto Sans JP', url: self::FONT_STYLESHEET, provider: LocalFontProvider::class)
            // avatars drawn here: the default sends every user's name to an outside service
            ->defaultAvatarProvider(InitialAvatarProvider::class)
            // the look (core::filament.theme) and the Alpine components of the app lists (core::filament.app-filter-script),
            // as a stylesheet and a script the browser keeps
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => ThemeAssets::headTags())
            // an app opens with the sidebar collapsed; other screens get back the state the user had before the apps
            ->renderHook(PanelsRenderHook::BODY_START, fn (array $scopes): string => AppSidebarState::script($scopes))
            // no top bar: the screens use the whole height; the sidebar hides completely and a floating button opens it
            // (core::filament.design), the search, the bell and the user menu are in the sidebar
            ->topbar(false)
            ->sidebarFullyCollapsibleOnDesktop()
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->userMenu(position: UserMenuPosition::Sidebar)
            // 通知 (bell), sent through Modules\Core\Application\Notifications\Notifier (docs/notifications.md)
            ->databaseNotifications(position: DatabaseNotificationsPosition::Sidebar)
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
            // Profile icon: プロフィール, ダッシュボード選択, ログアウト (password changes live on the profile page).
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action->label('プロフィール')->icon(Heroicon::OutlinedUserCircle),
                Action::make('portal')
                    ->label('ダッシュボード選択')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (): string => route('core.portal')),
            ])
            // Every business dashboard gets the administrators' ダッシュボード設定 (spaces, app permissions).
            ->plugins(DashboardAccess::isAdminOnly($id) ? [] : [DashboardSettingsPlugin::make()]);
    }
}
