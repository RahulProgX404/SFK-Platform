<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;

final class CreateConsumable extends AppCreateRecord
{
    protected static string $resource = ConsumableResource::class;

    protected static ?string $title = '消耗品を追加';

    /**
     * レコードを再利用する: the legacy id is not copied (名称 is, and must then be changed — unique).
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['legacy_id'];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
