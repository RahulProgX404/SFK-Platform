<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Core\Filament\AppPages\AppCreateRecord;

final class CreatePastSale extends AppCreateRecord
{
    protected static string $resource = PastSaleResource::class;

    protected static ?string $title = '過去の売上を追加';

    /**
     * レコードを再利用する: the legacy id is not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id', 'total'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
