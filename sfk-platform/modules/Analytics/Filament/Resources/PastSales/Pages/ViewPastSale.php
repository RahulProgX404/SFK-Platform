<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Modules\Analytics\Domain\AnalyticsFields;
use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Filament\AppPages\AppViewRecord;

/**
 * A record of 22. 過去の売上データ.
 *
 * @property PastSale $record
 */
final class ViewPastSale extends AppViewRecord
{
    protected static string $resource = PastSaleResource::class;

    public function getTitle(): string
    {
        return PastSaleResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return AnalyticsFields::PAST_SALES;
    }
}
