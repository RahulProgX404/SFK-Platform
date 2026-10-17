<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;

/** アプリの設定 → 一覧 of 03. 機械マスタ. */
final class MachineListViewSettings extends AppListViewSettings
{
    protected static string $resource = MachineResource::class;
}
