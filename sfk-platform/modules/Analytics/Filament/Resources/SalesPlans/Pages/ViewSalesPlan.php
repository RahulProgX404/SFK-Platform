<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use Modules\Analytics\Domain\AnalyticsFields;
use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Filament\AppPages\AppViewRecord;

/**
 * A record of 25. 販売先別売上計画.
 *
 * @property SalesPlan $record
 */
final class ViewSalesPlan extends AppViewRecord
{
    protected static string $resource = SalesPlanResource::class;

    public function getTitle(): string
    {
        return SalesPlanResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return AnalyticsFields::SALES_PLAN;
    }
}
