<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\MasterData\Domain\Partners\PartnerFields;
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

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PartnerFields::LABELS;
    }

    /** ‹ › follow the list order of 取引先一覧 (取引先コード). */
    protected function recordNavigationColumn(): string
    {
        return 'code';
    }
}
