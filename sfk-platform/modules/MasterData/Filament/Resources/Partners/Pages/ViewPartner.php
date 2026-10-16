<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

/** @property Partner $record */
final class ViewPartner extends AppViewRecord
{
    protected static string $resource = PartnerResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /** ‹ › follow the list order of 取引先一覧 (取引先コード). */
    protected function recordNavigationColumn(): string
    {
        return 'code';
    }
}
