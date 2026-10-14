<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Pages;

use Filament\Pages\Dashboard;

/** Home of the settings panel: entry points to every settings area plus key figures. */
final class SettingsDashboard extends Dashboard
{
    protected static ?string $title = '設定ダッシュボード';

    protected static ?string $navigationLabel = 'ダッシュボード';

    public function getSubheading(): string
    {
        return 'システム全体の設定をここから管理します。';
    }
}
