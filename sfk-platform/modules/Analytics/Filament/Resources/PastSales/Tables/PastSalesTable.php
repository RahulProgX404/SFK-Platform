<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Filament\Resources\PastSales\PastSaleLayout;
use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;

/**
 * Record list of 22. 過去の売上データ with the legacy view 売上一覧 (SYSTEM_BRAIN APP_22 views.md, newest first) and the
 * legacy graphs as カスタマイズ形式 views (ListPastSales): 年間売上 (年間合計 by 担当者 and 決算年度) and 担当者別売上比較
 * (largest / smallest 年間合計 by 担当者). インライン編集 changes 異常値フラグ and the months.
 */
final class PastSalesTable
{
    public const VIEW_LIST = 'list';

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'salesperson', 'fiscal_year', ...FiscalYear::columns(), 'subtotal', 'total', 'fiscal_month_start', 'date_label', 'year_number', 'date_number',
            'era', 'flag', 'record_key', 'created_at'] as $name) {
            $columns[$name] = PastSaleLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [
            ListView::table(self::VIEW_LIST, '売上一覧', ['salesperson', 'fiscal_year', ...FiscalYear::columns(), 'subtotal', 'total'], [['field' => 'id', 'direction' => 'desc']]),
            ListView::custom('annual', '年間売上', 'annual'),
            ListView::custom('range', '担当者別売上比較', 'range'),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : array_keys(self::columns());

        return $table
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(PastSaleResource::class))
            ->emptyStateIcon(Heroicon::OutlinedChartBarSquare)
            ->emptyStateHeading('過去の売上データがありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_22 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => PastSaleLayout::label($name);

        return [
            FilterField::text('salesperson', $label('salesperson')),
            FilterField::choice('flag', $label('flag'), array_combine(PastSale::FLAGS, PastSale::FLAGS)),
            FilterField::date('fiscal_month_start', $label('fiscal_month_start')),
            FilterField::number('total', $label('total')),
            ...array_map(fn (string $column): FilterField => FilterField::number($column, $label($column)), FiscalYear::columns()),
            FilterField::text('record_key', $label('record_key')),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [
            InlineField::select('flag', array_combine(PastSale::FLAGS, PastSale::FLAGS))->required(),
            ...array_map(fn (string $column): InlineField => InlineField::number($column)->minValue(-99999999999)->maxValue(99999999999), FiscalYear::columns()),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(PastSaleLayout::label($name))->placeholder('');
        $byStart = fn (Builder $query, string $direction): Builder => $query->orderBy('past_sales.fiscal_month_start', $direction);

        return match (true) {
            in_array($name, ['salesperson', 'record_key'], true) => $column->searchable(),
            in_array($name, FiscalYear::columns(), true), $name === 'total' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'subtotal' => $column->alignEnd()->state(fn (PastSale $record): string => AppFormat::yen($record->total))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('past_sales.total', $direction)),
            $name === 'fiscal_month_start' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'fiscal_year' => $column->state(fn (PastSale $record): ?string => $record->fiscalYear())->sortable(query: $byStart),
            $name === 'date_label' => $column->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Y年m月d日'))->sortable(query: $byStart),
            $name === 'year_number' => $column->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Y'))->sortable(query: $byStart),
            $name === 'date_number' => $column->state(fn (PastSale $record): ?string => $record->fiscal_month_start?->format('Ymd'))->sortable(query: $byStart),
            $name === 'era' => $column->state(fn (PastSale $record): ?string => $record->fiscal_month_start === null ? null : FiscalYear::era($record->fiscal_month_start))
                ->sortable(query: $byStart),
            default => $column,
        };
    }
}
