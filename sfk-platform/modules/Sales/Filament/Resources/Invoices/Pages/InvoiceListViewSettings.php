<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;

/** アプリの設定 → 一覧 of 11. 請求書管理(売上). */
final class InvoiceListViewSettings extends AppListViewSettings
{
    protected static string $resource = InvoiceResource::class;
}
