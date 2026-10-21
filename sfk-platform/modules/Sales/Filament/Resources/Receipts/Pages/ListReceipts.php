<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receipts\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Receipts\Tables\ReceiptsTable;

final class ListReceipts extends AppListRecords
{
    protected static string $resource = ReceiptResource::class;

    public function listColumns(): array
    {
        return ReceiptsTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN views.md of the app). */
    public function defaultListViews(): array
    {
        return ReceiptsTable::defaultViews();
    }

    public function filterFields(): array
    {
        return ReceiptsTable::filterFields();
    }

    public function inlineFields(): array
    {
        return ReceiptsTable::inlineFields();
    }
}
