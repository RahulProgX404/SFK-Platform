<?php

declare(strict_types=1);

namespace Modules\Erp\Filament;

use App\Modules\Filament\BasePanelProvider;
use App\Modules\Module;
use App\Modules\ModuleManager;
use Filament\Contracts\Plugin;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Livewire\Livewire;
use Modules\Core\Filament\Calendar\CompanyCalendarPage;
use Modules\Core\Filament\Portal\PortalPage;

/**
 * The main ERP panel (/erp, dashboard 白石封筒). Its home is the dashboard (PortalPage), the one screen with the top bar:
 * アプリ (all the apps over the whole screen, in place of a search box — the search stays in the sidebar), the bell and
 * the user menu; the app screens have none (BasePanelProvider).
 * It contains no business screens itself: every enabled business module whose module.json says
 * `"panel": "erp"` contributes its Filament plugin here.
 * Core adds 会社カレンダー (CompanyCalendarPage): read-only for everyone, the first item of the sidebar.
 */
final class ErpPanelProvider extends BasePanelProvider
{
    public const PANEL_ID = 'erp';

    public function panel(Panel $panel): Panel
    {
        return $this->configureBase(
            $panel,
            id: self::PANEL_ID,
            path: (string) config('erp.panel.path', 'erp'),
            domain: config('erp.panel.domain'),
            brand: (string) config('erp.panel.brand'),
        )
            ->default()
            ->topbar(fn (): bool => self::onHome())
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): string => self::onHome() ? view('core::filament.app-launcher-button')->render() : '')
            ->userMenu(position: fn (): UserMenuPosition => self::onHome() ? UserMenuPosition::Topbar : UserMenuPosition::Sidebar)
            ->databaseNotifications(position: fn (): DatabaseNotificationsPosition => self::onHome() ? DatabaseNotificationsPosition::Topbar : DatabaseNotificationsPosition::Sidebar)
            ->navigationGroups(NavigationGroup::labels())
            ->pages([
                PortalPage::class,
                CompanyCalendarPage::class,
            ])
            ->plugins($this->businessModulePlugins());
    }

    /** Whether the screen is the panel's home (/erp), also while Livewire updates it. */
    private static function onHome(): bool
    {
        $path = Livewire::isLivewireRequest() ? (string) Livewire::originalPath() : request()->path();

        return trim($path, '/') === trim((string) config('erp.panel.path', 'erp'), '/');
    }

    /** @return list<Plugin> */
    private function businessModulePlugins(): array
    {
        $modules = $this->app->make(ModuleManager::class)->forPanel(self::PANEL_ID);

        return array_values(array_map(
            fn (Module $module): Plugin => $this->app->make((string) $module->filamentPlugin),
            array_filter($modules, fn (Module $module): bool => $module->filamentPlugin !== null),
        ));
    }
}
