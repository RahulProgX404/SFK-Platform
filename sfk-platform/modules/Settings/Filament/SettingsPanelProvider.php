<?php

declare(strict_types=1);

namespace Modules\Settings\Filament;

use App\Modules\Filament\BasePanelProvider;
use Filament\Panel;

/**
 * ERP 共通管理 (/settings): platform settings — user management, organizations & positions, and future
 * system-wide settings. Users need "panel.settings.access" (or super-admin).
 */
final class SettingsPanelProvider extends BasePanelProvider
{
    public const PANEL_ID = 'settings';

    public function panel(Panel $panel): Panel
    {
        return $this->configureBase(
            $panel,
            id: self::PANEL_ID,
            path: (string) config('settings.panel.path', 'settings'),
            domain: config('settings.panel.domain'),
            brand: (string) config('settings.panel.brand', 'ERP 共通管理'),
        )
            ->navigationGroups(NavigationGroup::labels())
            ->discoverResources(in: __DIR__.'/Resources', for: 'Modules\\Settings\\Filament\\Resources')
            ->discoverPages(in: __DIR__.'/Pages', for: 'Modules\\Settings\\Filament\\Pages')
            ->discoverWidgets(in: __DIR__.'/Widgets', for: 'Modules\\Settings\\Filament\\Widgets');
    }
}
