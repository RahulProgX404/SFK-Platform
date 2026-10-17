<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Money\UnitPrice;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Domain\Machines\MachineFields;
use Modules\MasterData\Filament\Resources\Machines\MachineLayout;
use Modules\MasterData\Filament\Resources\Machines\MachineResource;

/**
 * Record list of 03. 機械マスタ. The legacy view 「機械一覧」 (SYSTEM_BRAIN APP_03 views.md: its columns, newest
 * 機械マスタID first) is the default view, （すべて） shows columns() in order. The search box replaces the
 * 「機会一覧(検索特化)」 plugin view (its name had a typo). filterFields() are the fields of 絞り込む and of the 一覧
 * settings; inlineFields() those インライン編集 changes, with the rules of MachineForm.
 */
final class MachinesTable
{
    public const VIEW_LIST = 'list';

    /** Columns of the legacy view 機械一覧, in order. */
    public const LIST_COLUMNS = ['code', 'name', ...MachineFields::RATES];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', ...self::LIST_COLUMNS, 'notes'] as $name) {
            $columns[$name] = MachineLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '機械一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->columns([
                IconColumn::make('record')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(MachineResource::class))
            ->emptyStateIcon(Heroicon::OutlinedCog6Tooth)
            ->emptyStateHeading('機械がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_03 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => MachineLayout::label($name);

        return [
            FilterField::number('id', $label('id')),
            FilterField::text('code', $label('code')),
            FilterField::text('name', $label('name')),
            ...array_map(fn (string $rate): FilterField => FilterField::number($rate, $label($rate)), MachineFields::RATES),
            FilterField::textarea('notes', $label('notes')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /**
     * Fields of インライン編集, checked like MachineForm (機械コード / 機械名 unique, 機械名 required).
     *
     * @return list<InlineField>
     */
    public static function inlineFields(): array
    {
        return [
            InlineField::text('code')->maxLength(64)->unique('machines'),
            InlineField::text('name')->required()->maxLength(64)->unique('machines'),
            ...array_map(
                fn (string $rate): InlineField => InlineField::number($rate, UnitPrice::SCALE)->minValue(0)->maxValue(9999999999),
                MachineFields::RATES,
            ),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(MachineLayout::label($name))
            ->placeholder('');

        return match (true) {
            $name === 'name' => $column->searchable()->sortable(),
            $name === 'code' => $column->searchable()->sortable(),
            in_array($name, MachineFields::RATES, true) => $column
                ->alignEnd()
                ->formatStateUsing(fn (mixed $state): string => UnitPrice::format(is_scalar($state) ? (string) $state : null)),
            default => $column,
        };
    }
}
