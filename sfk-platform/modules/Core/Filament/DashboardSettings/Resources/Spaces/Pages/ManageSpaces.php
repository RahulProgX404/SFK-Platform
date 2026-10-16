<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\DashboardSettings\Concerns\HasSettingsChrome;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Models\Space;

/** スペース of the dashboard, in the frame of the app screens. */
final class ManageSpaces extends ManageRecords
{
    use HasSettingsChrome;

    protected static string $resource = SpaceResource::class;

    protected static ?string $title = 'スペース';

    public function settingsPageKind(): string
    {
        return 'list';
    }

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
