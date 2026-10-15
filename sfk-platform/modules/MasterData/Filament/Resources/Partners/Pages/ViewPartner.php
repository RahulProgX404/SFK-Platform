<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
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

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('編集する')
                ->icon(Heroicon::OutlinedPencil)
                ->visible(fn (Partner $record): bool => PartnerResource::canEdit($record)),
            DeleteAction::make()
                ->label('削除する')
                ->color('gray')
                ->visible(fn (Partner $record): bool => PartnerResource::canDelete($record)),
        ];
    }
}
