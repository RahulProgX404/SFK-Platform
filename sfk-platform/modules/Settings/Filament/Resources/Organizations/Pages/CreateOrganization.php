<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Settings\Filament\Resources\Organizations\OrganizationResource;

final class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    protected static ?string $title = '組織を登録';

    /** Continue on the edit page, where positions are added. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
