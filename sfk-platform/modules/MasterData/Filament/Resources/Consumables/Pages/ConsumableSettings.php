<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;

/** アプリの設定 → 設定 of 19. 消耗品マスタ. */
final class ConsumableSettings extends AppSettings
{
    protected static string $resource = ConsumableResource::class;
}
