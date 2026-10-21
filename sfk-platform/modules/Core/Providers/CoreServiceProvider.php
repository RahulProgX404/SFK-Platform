<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Console\SyncAppsCommand;
use Modules\Core\Console\SyncSuperAdminCommand;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Core\Http\Portal\DashboardDirectory;
use Modules\Core\Http\Portal\DashboardEntry;
use Modules\Core\Http\Responses\LoginPageLogoutResponse;

final class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'core';

    protected array $commands = [
        SyncSuperAdminCommand::class,
        SyncAppsCommand::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(DocumentNumberGenerator::class);
        $this->app->singleton(RelatedLists::class);

        // Apps are discovered from the AppResource screens of every module (enabled or not).
        $this->app->bind(AppDiscovery::class, fn (): AppDiscovery => new AppDiscovery([base_path('modules')]));

        // Dashboards have no login page of their own: logging out returns to the single /login page.
        $this->app->bind(LogoutResponse::class, LoginPageLogoutResponse::class);
    }

    protected function bootModule(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // The dashboard name (sidebar header and topbar) leads to the dashboard's ポータル; the first menu group
        // ダッシュボード切替 lists the dashboards the user may open. Registered once, globally, so they
        // work for every panel regardless of which one booted.
        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('sidebar'));
        FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('topbar'));
        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): string => self::dashboardMenu());
    }

    private static function dashboardSwitcher(string $place): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return view('core::filament.dashboard-switcher', [
            'place' => $place,
            'currentLabel' => DashboardAccess::label($panel),
            'homeUrl' => $panel->getUrl(),
        ])->render();
    }

    private static function dashboardMenu(): string
    {
        $user = auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();
        $dashboards = $user instanceof User ? app(DashboardDirectory::class)->for($user) : [];
        if ($dashboards === []) {
            return '';
        }

        return view('core::filament.dashboard-menu', [
            'items' => array_map(fn (DashboardEntry $dashboard): NavigationItem => NavigationItem::make($dashboard->label)
                ->url($dashboard->url)
                ->icon(Heroicon::OutlinedSquares2x2)
                ->isActiveWhen(fn (): bool => $dashboard->id === $panel->getId()), $dashboards),
        ])->render();
    }
}
