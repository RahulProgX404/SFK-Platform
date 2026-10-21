<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;

/** アプリの設定 → 一覧 of 21. 売入金管理（売上）. */
final class ReceiptListViewSettings extends AppListViewSettings
{
    protected static string $resource = ReceiptResource::class;
}
