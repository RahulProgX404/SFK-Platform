<?php

declare(strict_types=1);

namespace Modules\Erp\Filament;

use App\Modules\Filament\BasePanelProvider;
use App\Modules\Module;
use App\Modules\ModuleManager;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Core\Filament\Portal\PortalPage;

/**
 * The main ERP panel (/erp, dashboard 白石封筒). Its home is the legacy-style ポータル (お知らせ, スペース, アプリ).
 * It contains no business screens itself: every enabled business module whose module.json says
 * `"panel": "erp"` contributes its Filament plugin here.
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
            // Collapsing the menu hides it completely; the content then uses the full screen width.
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop()
            ->navigationGroups(NavigationGroup::labels())
            ->pages([
                PortalPage::class,
            ])
            ->plugins($this->businessModulePlugins());
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
