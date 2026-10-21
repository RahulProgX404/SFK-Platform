<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;

/** アプリの設定 → 一覧 of 23. 買掛管理. */
final class PayableListViewSettings extends AppListViewSettings
{
    protected static string $resource = PayableResource::class;
}
