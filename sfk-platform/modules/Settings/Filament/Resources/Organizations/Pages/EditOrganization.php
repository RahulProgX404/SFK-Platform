<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Models\Organization;
use Modules\Settings\Filament\Resources\Organizations\OrganizationResource;

/** @property Organization $record */
final class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    public function getTitle(): string
    {
        return "{$this->record->name} の編集";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('この組織と、その役職・所属情報をすべて削除します。下位組織は最上位の組織になります。この操作は元に戻せません。'),
        ];
    }
}
