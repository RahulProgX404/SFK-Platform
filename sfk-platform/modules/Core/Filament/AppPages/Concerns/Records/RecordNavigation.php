<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Records;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * ‹ › of the record page: the records before and after this one in the app's default list order
 * (recordNavigationColumn()), both read in one query when the page opens and kept with the page.
 *
 * Part of Modules\Core\Filament\AppPages\AppViewRecord (the shared record page).
 */
trait RecordNavigation
{
    /**
     * ‹ › of the header (recordNeighbours()), worked out when the page opens and kept with the page: the header is drawn
     * again on every Livewire request of the page, which asked the database twice each time (performance pass
     * 2026-11-20).
     *
     * @var array{previous: string|null, next: string|null}|null
     */
    #[Locked]
    public ?array $neighbourUrls = null;

    /** Column of the app's default list order, followed by ‹ › (the key breaks ties; a blank value navigates by key). */
    protected function recordNavigationColumn(): string
    {
        return $this->record->getKeyName();
    }

    /** @return array{previous: string|null, next: string|null} */
    public function recordNeighbours(): array
    {
        if ($this->neighbourUrls !== null) {
            return $this->neighbourUrls;
        }

        $resource = static::getResource();
        $urls = [];
        $neighbours = $this->neighbours();

        foreach (['previous', 'next'] as $direction) {
            $neighbour = $neighbours[$direction];
            $urls[$direction] = $neighbour !== null && $resource::canView($neighbour)
                ? $resource::getUrl('view', ['record' => $neighbour])
                : null;
        }

        return $this->neighbourUrls = $urls;
    }

    /**
     * The records before and after this one in the app's default order — both in one query.
     *
     * @return array{previous: Model|null, next: Model|null}
     */
    private function neighbours(): array
    {
        $rows = $this->neighbourQuery(false)->unionAll($this->neighbourQuery(true)->toBase())->get();

        $found = ['previous' => null, 'next' => null];
        foreach ($rows as $row) {
            $direction = $row->getAttribute('sfk_neighbour') === 'next' ? 'next' : 'previous';
            $row->offsetUnset('sfk_neighbour');
            $row->syncOriginal();
            $found[$direction] = $row;
        }

        return $found;
    }

    /** @return Builder<Model> */
    private function neighbourQuery(bool $forward): Builder
    {
        $record = $this->record;
        $key = $record->getQualifiedKeyName();
        $id = $record->getKey();
        $value = $record->getAttribute($this->recordNavigationColumn());
        $column = $value === null ? $key : $record->qualifyColumn($this->recordNavigationColumn());
        $value ??= $id;
        $operator = $forward ? '>' : '<';
        $direction = $forward ? 'asc' : 'desc';

        $query = static::getResource()::getEloquentQuery();
        if ($query->getQuery()->columns === null) {
            $query->select($record->getTable().'.*');
        }

        return $query
            ->selectRaw('cast(? as varchar(8)) as sfk_neighbour', [$forward ? 'next' : 'previous'])
            ->where(fn (Builder $query): Builder => $query
                ->where($column, $operator, $value)
                ->orWhere(fn (Builder $query): Builder => $query->where($column, $value)->where($key, $operator, $id)))
            ->orderBy($column, $direction)
            ->orderBy($key, $direction)
            ->limit(1);
    }
}
