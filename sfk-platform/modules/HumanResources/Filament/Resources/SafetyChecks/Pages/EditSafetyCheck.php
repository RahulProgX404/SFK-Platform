<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Models\SafetyCheck;

/** @property SafetyCheck $record */
final class EditSafetyCheck extends AppEditRecord
{
    protected static string $resource = SafetyCheckResource::class;

    public function getTitle(): string
    {
        return SafetyCheckResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
