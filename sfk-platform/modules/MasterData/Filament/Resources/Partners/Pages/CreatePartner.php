<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

final class CreatePartner extends AppCreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected static ?string $title = '取引先を追加';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $roles = (array) ($data['roles'] ?? []);
        unset($data['roles']);

        return [...$data, ...Partner::roleColumns($roles)];
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
