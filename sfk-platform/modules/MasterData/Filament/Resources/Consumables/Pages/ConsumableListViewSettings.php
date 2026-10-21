<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;

/** アプリの設定 → 一覧 of 19. 消耗品マスタ. */
final class ConsumableListViewSettings extends AppListViewSettings
{
    protected static string $resource = ConsumableResource::class;
}
