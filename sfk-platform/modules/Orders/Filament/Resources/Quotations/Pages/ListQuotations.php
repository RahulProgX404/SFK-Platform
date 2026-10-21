<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Quotations\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Filament\Resources\Quotations\Tables\QuotationsTable;

final class ListQuotations extends AppListRecords
{
    protected static string $resource = QuotationResource::class;

    public function listColumns(): array
    {
        return QuotationsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_09 views.md). */
    public function defaultListViews(): array
    {
        return QuotationsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return QuotationsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return QuotationsTable::inlineFields();
    }
}
