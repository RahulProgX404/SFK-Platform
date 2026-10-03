<?php

declare(strict_types=1);

namespace Modules\Erp\Filament;

use App\Modules\Filament\BasePanelProvider;
use App\Modules\Module;
use App\Modules\ModuleManager;
use Filament\Contracts\Plugin;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Widgets\AccountWidget;
use Modules\Erp\Filament\Widgets\ModulesOverviewWidget;

/**
 * The main ERP panel (/erp). It contains no business screens itself: every enabled business
 * module whose module.json says `"panel": "erp"` contributes its Filament plugin here.
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
            ->navigationGroups(NavigationGroup::labels())
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                ModulesOverviewWidget::class,
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
