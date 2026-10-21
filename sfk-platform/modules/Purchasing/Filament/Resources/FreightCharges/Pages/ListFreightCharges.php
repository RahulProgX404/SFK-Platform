<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Application\Imports\FreightCsvImport;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;
use Modules\Purchasing\Filament\Resources\FreightCharges\Tables\FreightChargesTable;
use Modules\Purchasing\Filament\Support\CsvImportAction;

/** List of 15. 運送請求データ取込. 「…」 has ファイルから読み込む (FreightCsvImport). */
final class ListFreightCharges extends AppListRecords
{
    protected static string $resource = FreightChargeResource::class;

    public function listColumns(): array
    {
        return FreightChargesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_15 views.md). */
    public function defaultListViews(): array
    {
        return FreightChargesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return FreightChargesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return FreightChargesTable::inlineFields();
    }

    protected function optionActions(): array
    {
        return [CsvImportAction::make(FreightChargeResource::class, fn (string $contents): int => app(FreightCsvImport::class)->import($contents))];
    }
}
