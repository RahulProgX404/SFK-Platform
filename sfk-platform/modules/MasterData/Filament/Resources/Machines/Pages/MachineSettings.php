<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;

/** アプリの設定 → 設定 of 03. 機械マスタ. */
final class MachineSettings extends AppSettings
{
    protected static string $resource = MachineResource::class;
}
