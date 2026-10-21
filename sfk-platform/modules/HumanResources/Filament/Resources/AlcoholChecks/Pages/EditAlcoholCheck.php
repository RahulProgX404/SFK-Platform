<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Models\AlcoholCheck;

/** @property AlcoholCheck $record */
final class EditAlcoholCheck extends AppEditRecord
{
    protected static string $resource = AlcoholCheckResource::class;

    public function getTitle(): string
    {
        return AlcoholCheckResource::titleOf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
