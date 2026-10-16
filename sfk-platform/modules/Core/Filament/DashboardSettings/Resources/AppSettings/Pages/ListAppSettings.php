<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;

/** アプリの権限 of the dashboard's apps, in the frame of the app screens. */
final class ListAppSettings extends ListRecords
{
    use HasSettingsChrome;

    protected static string $resource = AppSettingsResource::class;

    protected static ?string $title = 'アプリの権限';

    public function settingsPageKind(): string
    {
        return 'list';
    }
}
