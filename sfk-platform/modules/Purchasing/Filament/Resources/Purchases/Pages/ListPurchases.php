<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Application\Imports\PurchaseCsvImport;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Filament\Resources\Purchases\Tables\PurchasesTable;
use Modules\Purchasing\Filament\Support\CsvImportAction;

/**
 * List of 05. 納品(仕入)入力. 「…」 has ファイルから読み込む (PurchaseCsvImport) — the imported purchases are numbered and
 * posted at once, so the legacy list buttons 納品(仕入)入力No発番 / 受注レコード更新 / 買掛レコード更新 are not needed.
 */
final class ListPurchases extends AppListRecords
{
    protected static string $resource = PurchaseResource::class;

    public function listColumns(): array
    {
        return PurchasesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_05 views.md). */
    public function defaultListViews(): array
    {
        return PurchasesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return PurchasesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return PurchasesTable::inlineFields();
    }

    protected function optionActions(): array
    {
        return [CsvImportAction::make(PurchaseResource::class, fn (string $contents): int => app(PurchaseCsvImport::class)->import($contents))];
    }
}
