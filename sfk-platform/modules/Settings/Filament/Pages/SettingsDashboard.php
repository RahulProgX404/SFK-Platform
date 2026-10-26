<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Pages;

use Filament\Pages\Dashboard;

/**
 * Home of the settings panel: entry points to every settings area plus key figures. Not a menu entry of its own:
 * 共通管理 under ダッシュボード切替 opens it.
 */
final class SettingsDashboard extends Dashboard
{
    protected static ?string $title = '設定ダッシュボード';

    protected static ?string $navigationLabel = 'ダッシュボード';

    protected static bool $shouldRegisterNavigation = false;
}
