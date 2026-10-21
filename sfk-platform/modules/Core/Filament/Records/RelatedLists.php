<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use Closure;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Related-record lists one module shows on the record page of another module's app (e.g. 仕入履歴 of 05 on a 04 record,
 * where 04 cannot depend on the module owning the records): that module registers the list of a slot in its service
 * provider, the page shows it with the related-table view — nothing (not even a message) while no module provides it
 * or there are no records.
 */
final class RelatedLists
{
    /** @var array<string, Closure(Model): array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}> */
    private array $providers = [];

    /** @param  Closure(Model): array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}  $list */
    public function provide(string $slot, Closure $list): void
    {
        $this->providers[$slot] = $list;
    }

    /**
     * The list of a slot for a record, with its title.
     *
     * @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>}
     */
    public function data(string $slot, Model $record, string $title): array
    {
        if (! isset($this->providers[$slot])) {
            return ['title' => '', 'columns' => [], 'numeric' => [], 'rows' => [], 'urls' => []];
        }

        return ['title' => $title, ...($this->providers[$slot])($record)];
    }

    /** The record-page component of a slot. */
    public static function entry(string $slot, string $title): View
    {
        return View::make('core::filament.related-table')
            ->viewData(fn (Model $record): array => app(self::class)->data($slot, $record, $title))
            ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false));
    }
}
