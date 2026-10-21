<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Models\AlcoholCheck;

/**
 * A record of 18. アルコールチェック.
 *
 * @property AlcoholCheck $record
 */
final class ViewAlcoholCheck extends AppViewRecord
{
    protected static string $resource = AlcoholCheckResource::class;

    public function getTitle(): string
    {
        return AlcoholCheckResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return HrFields::ALCOHOL;
    }
}
