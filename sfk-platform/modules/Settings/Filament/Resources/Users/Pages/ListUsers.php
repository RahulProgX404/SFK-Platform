<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Settings\Filament\Resources\Users\UserResource;
use Modules\Settings\Filament\Resources\Users\Widgets\UserStatsOverview;

/** ユーザー管理 home: headline figures on top, user list with CRUD below. */
final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'ユーザー管理';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('ユーザーを登録')
                ->icon(Heroicon::OutlinedUserPlus),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserStatsOverview::class,
        ];
    }
}
