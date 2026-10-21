<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;

/** アプリの設定 → 設定 of 11. 請求書管理(売上). */
final class InvoiceSettings extends AppSettings
{
    protected static string $resource = InvoiceResource::class;
}
