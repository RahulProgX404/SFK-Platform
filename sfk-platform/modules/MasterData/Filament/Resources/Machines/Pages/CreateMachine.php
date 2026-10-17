<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;

final class CreateMachine extends AppCreateRecord
{
    protected static string $resource = MachineResource::class;

    protected static ?string $title = '機械を追加';

    /**
     * レコードを再利用する: the legacy id is not copied (機械コード / 機械名 are, and must then be changed — unique).
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
