<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;

/** アプリの設定 → 設定 of 23-2. 売掛管理(締日別). */
final class CycleReceivableSettings extends AppSettings
{
    protected static string $resource = CycleReceivableResource::class;
}
