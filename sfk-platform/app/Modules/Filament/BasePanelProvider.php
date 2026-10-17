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
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\Profile\ProfilePage;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

/**
 * Common configuration for every Filament panel in the platform (ERP panel and extension panels):
 * standard middleware stack, login page, shared users/guard, colours and branding.
 *
 * Look & feel follows the legacy system, which the staff already know: light theme only, the legacy vendor's
 * published UI-part colours and sizes, flat square boxes and a Meiryo-first font (core::filament.theme).
 *
 * Panels differ only in id, path/domain, brand label and which components they load.
 * Access to each panel is checked in User::canAccessPanel() (user / 従業員カテゴリ dashboards and ユーザー種別,
 * see Modules\Core\Access\DashboardAccess). Core replaces the brand with the dashboard name, which opens a dashboard switcher.
 */
abstract class BasePanelProvider extends PanelProvider
{
    /** the legacy system's action blue; also used by the portal and the charts. */
    public const BRAND_BLUE = '#3498db';

    public const BRAND_RED = '#e74c3c';

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
            // System Japanese fonts (no web-font download); the full stack is set by the theme view.
            ->font('Meiryo', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('core::filament.theme')->render())
            // 絞り込む dialog of the app lists (core::filament.app-filter)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('core::filament.app-filter-script')->render())
            // paging of the app lists, right of the search box
            ->renderHook(TablesRenderHook::TOOLBAR_END, fn (): string => AppListRecords::toolbarPager())
            ->sidebarCollapsibleOnDesktop()
            // 通知 (bell in the top bar), e.g. a コメント on a record (Modules\Core\Application\Comments\RecordComments)
            ->databaseNotifications()
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
