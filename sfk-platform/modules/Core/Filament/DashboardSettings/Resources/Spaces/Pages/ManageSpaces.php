<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Models\Space;

final class ManageSpaces extends ManageRecords
{
    protected static string $resource = SpaceResource::class;

    protected static ?string $title = 'スペース';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('スペースを作成')
                ->icon(Heroicon::OutlinedPlus)
                ->using(fn (array $data): Space => SpaceResource::save(null, $data)),
        ];
    }
}
