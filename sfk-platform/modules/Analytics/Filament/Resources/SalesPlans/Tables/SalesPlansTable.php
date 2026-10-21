<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Tables;

use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanLayout;
use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;

/**
 * Record list of 25. 販売先別売上計画 with the legacy view 販売先別売上計画 (SYSTEM_BRAIN APP_25 views.md: by グループ名 — the
 * list's order —, 計画値 and the actuals by month); （すべて） shows columns() in order, the monthly plans as 「05月(計画)」.
 * インライン編集 changes 計画値, 中分類 and 備考.
 */
final class SalesPlansTable
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
        foreach (['id', 'customer_id', 'customer_name', 'partner_group', 'segment', 'planned_on', 'year', 'actual_total', ...FiscalYear::columns('actual_'), 'plan_total',
            'progress', 'notes', 'customer_code', 'legacy_customer_code', 'sub_category', ...FiscalYear::columns('plan_'), 'created_at'] as $name) {
            $columns[$name] = SalesPlanLayout::columnLabel($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '販売先別売上計画', ['partner_group', 'segment', 'customer_name', 'year', 'plan_total', ...FiscalYear::columns('actual_'),
            'actual_total', 'progress'])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : array_keys(self::columns());

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer'))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            // legacy sort: グループ名
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(self::partnerColumn('partner_group'))->orderByDesc('sales_plans.id'))
            ->recordActions(AppRecordActions::make(SalesPlanResource::class))
            ->emptyStateIcon(Heroicon::OutlinedPresentationChartLine)
            ->emptyStateHeading('売上計画がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_25 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => SalesPlanLayout::columnLabel($name);

        return [
            FilterField::choice('customer_id', $label('customer_name'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::text('sub_category', $label('sub_category')),
            FilterField::date('planned_on', $label('planned_on')),
            FilterField::number('plan_total', $label('plan_total')),
            FilterField::number('actual_total', $label('actual_total')),
            ...array_map(fn (string $column): FilterField => FilterField::number($column, $label($column)), FiscalYear::columns('plan_')),
            ...array_map(fn (string $column): FilterField => FilterField::number($column, $label($column)), FiscalYear::columns('actual_')),
            FilterField::text('notes', $label('notes')),
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
            InlineField::number('plan_total')->minValue(0)->maxValue(99999999999),
            InlineField::text('sub_category')->maxLength(255),
            InlineField::text('notes')->maxLength(255),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(SalesPlanLayout::columnLabel($name))->placeholder('');
        $partner = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(self::partnerColumn($attribute), $direction);
        $byDate = fn (Builder $query, string $direction): Builder => $query->orderBy('sales_plans.planned_on', $direction);

        return match (true) {
            preg_match('/^(plan|actual)_m\d{2}$/', $name) === 1, $name === 'plan_total', $name === 'actual_total' => $column->alignEnd()
                ->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            in_array($name, ['sub_category', 'notes'], true) => $column->searchable(),
            $name === 'planned_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'year' => $column->state(fn (SalesPlan $record): ?string => $record->yearLabel())->sortable(query: $byDate),
            $name === 'progress' => $column->alignEnd()->state(fn (SalesPlan $record): ?string => SalesPlanLayout::percent($record->progress()))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    '(case when coalesce(sales_plans.plan_total, 0) = 0 then null else sales_plans.actual_total * 1.0 / sales_plans.plan_total end) '.($direction === 'desc' ? 'desc' : 'asc'),
                )),
            $name === 'customer_name' => $column->state(fn (SalesPlan $record): ?string => $record->customer?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            isset(SalesPlanLayout::MASTER[$name]) => $column
                ->state(fn (SalesPlan $record): ?string => AppFormat::text($record->customer?->getAttribute(SalesPlanLayout::MASTER[$name])))
                ->sortable(query: $partner(SalesPlanLayout::MASTER[$name])),
            default => $column,
        };
    }

    /** @return Builder<Partner> a column of the row's customer, for sorting */
    private static function partnerColumn(string $attribute): Builder
    {
        return Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'sales_plans.customer_id');
    }
}
