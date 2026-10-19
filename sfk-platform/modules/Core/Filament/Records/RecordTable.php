<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as EntryTableColumn;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * A legacy table field (サブテーブル) on a record form or record page: the columns keep their legacy widths (the
 * table scrolls sideways inside the record instead of squeezing the inputs), the form always shows at least one row
 * — the first row is there on a new record — and every row ends with ⊕ (行を追加する: a new row below it) and ⊖
 * (行を削除する, not on the last remaining row), like the legacy form. Rows left completely empty are not saved
 * (an existing row emptied is deleted). Drop-downs inside a table must be native selects so their list is not cut
 * off by the table's scroll box.
 */
final class RecordTable
{
    /** Width of the ⊕ / ⊖ column. */
    private const ACTIONS_PX = 64;

    /**
     * @param  list<array{0: string, 1: int}>  $columns  [label, width px] in the order of the repeater's schema
     */
    public static function form(Repeater $repeater, array $columns): Repeater
    {
        return $repeater
            ->table(array_map(fn (array $column): TableColumn => TableColumn::make($column[0])->width($column[1].'px'), $columns))
            ->defaultItems(1)
            ->addable(false)
            ->reorderable(false)
            ->extraAttributes(['class' => 'kt-subtable', 'style' => '--kt-subtable-width: '.(self::width($columns) + self::ACTIONS_PX).'px'])
            ->extraItemActions([self::insertAction()])
            ->deleteAction(fn (Action $action): Action => $action
                ->label('行を削除する')
                ->icon(Heroicon::MinusCircle)
                ->color('gray')
                ->size(Size::Large)
                ->visible(fn (Repeater $component): bool => count($component->getRawState() ?? []) > 1))
            ->afterStateHydrated(fn (Repeater $component) => self::ensureRow($component))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => self::isBlank($data) ? null : $data)
            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Model $record): ?array {
                if (self::isBlank($data)) {
                    $record->delete();

                    return null;
                }

                return $data;
            });
    }

    /**
     * The same table on the record page.
     *
     * @param  list<array{0: string, 1: int}>  $columns  [label, width px] in the order of the entry's schema
     */
    public static function entry(RepeatableEntry $entry, array $columns): RepeatableEntry
    {
        return $entry
            ->table(array_map(fn (array $column): EntryTableColumn => EntryTableColumn::make($column[0])->width($column[1].'px'), $columns))
            ->extraAttributes(['class' => 'kt-subtable', 'style' => '--kt-subtable-width: '.self::width($columns).'px']);
    }

    /**
     * A row with nothing filled in (the order column and unchecked boxes do not count).
     *
     * @param  array<string, mixed>  $data
     */
    public static function isBlank(array $data): bool
    {
        foreach ($data as $key => $value) {
            if ($key === 'sort' || $value === null || $value === '' || $value === false || $value === []) {
                continue;
            }

            return false;
        }

        return true;
    }

    /** ⊕: a new empty row below the row. */
    private static function insertAction(): Action
    {
        return Action::make('insertRow')
            ->label('行を追加する')
            ->icon(Heroicon::PlusCircle)
            ->color('primary')
            ->iconButton()
            ->size(Size::Large)
            ->action(function (array $arguments, Repeater $component): void {
                $newKey = $component->generateUuid();
                $items = [];
                foreach ($component->getRawState() ?? [] as $key => $item) {
                    $items[$key] = $item;
                    if ($key === ($arguments['item'] ?? null)) {
                        $newKey ? $items[$newKey] = [] : $items[] = [];
                        $newKey ??= array_key_last($items);
                    }
                }

                $component->rawState($items);
                $component->getChildSchema((string) $newKey)?->fill();
                $component->callAfterStateUpdated();
            });
    }

    /** The form always shows a row to fill in. */
    private static function ensureRow(Repeater $component): void
    {
        if (($component->getRawState() ?? []) !== []) {
            return;
        }

        $key = $component->generateUuid() ?? 0;
        $component->rawState([$key => []]);
        $component->getChildSchema((string) $key)?->fill();
    }

    /** @param  list<array{0: string, 1: int}>  $columns */
    private static function width(array $columns): int
    {
        return array_sum(array_map(fn (array $column): int => $column[1], $columns));
    }
}
