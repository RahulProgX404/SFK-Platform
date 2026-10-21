<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Core\Filament\AppPages\AppCreateRecord;

final class CreateSalesPlan extends AppCreateRecord
{
    protected static string $resource = SalesPlanResource::class;

    protected static ?string $title = '売上計画を追加';

    /**
     * レコードを再利用する: the legacy id and the derived total are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'actual_total'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
