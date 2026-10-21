<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Modules\Core\Filament\AppPages\AppSettings;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;

/** アプリの設定 → 設定 of 09. 見積書管理. */
final class QuotationSettings extends AppSettings
{
    protected static string $resource = QuotationResource::class;
}
