<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Models\SafetyCheck;

/**
 * A record of 17. 安全衛生チェック.
 *
 * @property SafetyCheck $record
 */
final class ViewSafetyCheck extends AppViewRecord
{
    protected static string $resource = SafetyCheckResource::class;

    public function getTitle(): string
    {
        return SafetyCheckResource::titleOf($this->record);
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return HrFields::SAFETY;
    }
}
