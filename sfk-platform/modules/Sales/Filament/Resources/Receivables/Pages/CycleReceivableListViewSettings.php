<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;

/** アプリの設定 → 一覧 of 23-2. 売掛管理(締日別). */
final class CycleReceivableListViewSettings extends AppListViewSettings
{
    protected static string $resource = CycleReceivableResource::class;
}
