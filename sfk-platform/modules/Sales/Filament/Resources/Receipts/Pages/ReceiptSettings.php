<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;

/** アプリの設定 → 設定 of 21. 売入金管理（売上）. */
final class ReceiptSettings extends AppSettings
{
    protected static string $resource = ReceiptResource::class;
}
