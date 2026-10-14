<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Settings\Filament\Resources\Organizations\OrganizationResource;

final class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected static ?string $title = '組織・役職';

    public function getSubheading(): string
    {
        return '組織を作成し、組織ごとに役職を登録します。行をドラッグすると表示順を変更できます。';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('組織を登録')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
