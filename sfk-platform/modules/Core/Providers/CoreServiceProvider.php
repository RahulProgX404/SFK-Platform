<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Console\SyncAppsCommand;
use Modules\Core\Console\SyncSuperAdminCommand;
use Modules\Core\Http\Portal\DashboardDirectory;
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

        // The dashboard name (sidebar header and topbar) opens a dropdown of the dashboards the user may
        // open. Registered once, globally, so it works for every panel regardless of which one booted.
        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('sidebar'));
        FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('topbar'));
    }

    private static function dashboardSwitcher(string $place): string
    {
        $user = auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        return view('core::filament.dashboard-switcher', [
            'place' => $place,
            'dashboards' => $user instanceof User ? app(DashboardDirectory::class)->for($user) : [],
            'current' => $panel->getId(),
            'currentLabel' => DashboardAccess::label($panel),
            'homeUrl' => $panel->getUrl(),
        ])->render();
    }
}
