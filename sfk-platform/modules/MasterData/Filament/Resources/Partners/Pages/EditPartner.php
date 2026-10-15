<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

/** @property Partner $record */
final class EditPartner extends AppEditRecord
{
    protected static string $resource = PartnerResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = array_map(fn (PartnerRole $role): string => $role->value, $this->record->roles());

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $roles = (array) ($data['roles'] ?? []);
        unset($data['roles']);

        return [...$data, ...Partner::roleColumns($roles)];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
