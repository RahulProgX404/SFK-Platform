<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * A record of an app: app frame; toolbar with the pencil (レコードを編集する) and the 「…」 menu (削除する) on the left,
 * gear and ‹ › to the previous / next record (in the app's list order, recordNavigationColumn()) on the right.
 */
abstract class AppViewRecord extends ViewRecord
{
    use HasAppChrome;

    public function appPageKind(): string
    {
        return 'view';
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return [
            EditAction::make()
                ->label('レコードを編集する')
                ->icon(Heroicon::OutlinedPencil)
                ->iconButton()
                ->color('gray')
                ->visible(fn (Model $record): bool => $resource::canEdit($record)),
            ActionGroup::make([
                DeleteAction::make()
                    ->label('削除する')
                    ->visible(fn (Model $record): bool => $resource::canDelete($record)),
            ])
                ->label('その他の操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->color('gray')
                ->iconButton(),
        ];
    }

    /** Column of the app's default list order, followed by ‹ › (the key breaks ties; a blank value navigates by key). */
    protected function recordNavigationColumn(): string
    {
        return $this->getRecord()->getKeyName();
    }

    /** @return array{previous: string|null, next: string|null} */
    public function recordNeighbours(): array
    {
        $resource = static::getResource();
        $urls = [];

        foreach (['previous' => false, 'next' => true] as $direction => $forward) {
            $neighbour = $this->neighbour($forward);
            $urls[$direction] = $neighbour !== null && $resource::canView($neighbour)
                ? $resource::getUrl('view', ['record' => $neighbour])
                : null;
        }

        return $urls;
    }

    private function neighbour(bool $forward): ?Model
    {
        $record = $this->getRecord();
        $key = $record->getQualifiedKeyName();
        $id = $record->getKey();
        $value = $record->getAttribute($this->recordNavigationColumn());
        $column = $value === null ? $key : $record->qualifyColumn($this->recordNavigationColumn());
        $value ??= $id;
        $operator = $forward ? '>' : '<';
        $direction = $forward ? 'asc' : 'desc';

        return static::getResource()::getEloquentQuery()
            ->where(fn (Builder $query): Builder => $query
                ->where($column, $operator, $value)
                ->orWhere(fn (Builder $query): Builder => $query->where($column, $value)->where($key, $operator, $id)))
            ->orderBy($column, $direction)
            ->orderBy($key, $direction)
            ->first();
    }
}
