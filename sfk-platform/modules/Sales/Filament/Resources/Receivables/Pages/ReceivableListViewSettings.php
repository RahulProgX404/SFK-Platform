<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;

/** アプリの設定 → 一覧 of 23. 売掛管理. */
final class ReceivableListViewSettings extends AppListViewSettings
{
    protected static string $resource = ReceivableResource::class;
}
