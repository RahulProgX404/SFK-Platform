<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Invoices\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Invoices\Tables\InvoicesTable;

final class ListInvoices extends AppListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function listColumns(): array
    {
        return InvoicesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN views.md of the app). */
    public function defaultListViews(): array
    {
        return InvoicesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return InvoicesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return InvoicesTable::inlineFields();
    }
}
