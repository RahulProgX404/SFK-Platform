<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AppSettingsResource;

final class ListAppSettings extends ListRecords
{
    protected static string $resource = AppSettingsResource::class;

    protected static ?string $title = 'アプリの権限';
}
