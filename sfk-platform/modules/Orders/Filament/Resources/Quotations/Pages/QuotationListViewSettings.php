<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Modules\Core\Filament\AppPages\AppListViewSettings;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;

/** アプリの設定 → 一覧 of 09. 見積書管理. */
final class QuotationListViewSettings extends AppListViewSettings
{
    protected static string $resource = QuotationResource::class;
}
