<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseLayout;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\Purchase;

/**
 * Record list of 05. 納品(仕入)入力 with the legacy view 納品(仕入)一覧 (SYSTEM_BRAIN APP_05 views.md, newest first);
 * （すべて） shows columns() in order (the legacy helper fields — keys, 超過チェック, 支払残, 連携 flags — are left out).
 * The search box replaces 納品(仕入)一覧(検索特化); the cross table 取引先別月別仕入金額 is not rebuilt here. インライン編集
 * changes the fields without copies: 納品(仕入)日, 仕入カテゴリ, 品名 and the amounts (金額 as entered).
 */
final class PurchasesTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['purchase_number', 'purchased_on', 'supplier_id', 'order_id', 'product_name', 'quantity', 'unit_price', 'amount',
        'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'total', 'is_fsc'];

    private const AMOUNTS = ['amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'total'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'purchased_on', 'purchase_number', 'supplier_id', 'order_id', 'product_name', 'unit_price', 'quantity', 'amount', 'cutting_amount',
            'plate_amount', 'stored_plate_amount', 'spot_color_amount', 'overruns', 'notified_users', 'master_category', 'purchase_month', 'supplier_code',
            'period_end', 'category', 'effective_category', 'total', 'tax_mode', 'supplier_rounding', 'is_fsc', 'created_at'] as $name) {
            $columns[$name] = PurchaseLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '納品(仕入)一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supplier', 'order', 'notifiedUsers']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(PurchaseResource::class))
            ->emptyStateIcon(Heroicon::OutlinedTruck)
            ->emptyStateHeading('納品(仕入)がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_05 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => PurchaseLayout::label($name);
        $modes = array_map(fn (TaxMode $mode): string => $mode->value, TaxMode::cases());

        return [
            FilterField::date('purchased_on', $label('purchased_on')),
            FilterField::choice('supplier_id', $label('supplier_id'), fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::choice('tax_mode', $label('tax_mode'), array_combine($modes, $modes))->relationship('supplier', 'supplier_tax_mode'),
            FilterField::choice('master_category', $label('master_category'), PurchaseLayout::categoryOptions())->relationship('supplier', 'purchase_category'),
            FilterField::choice('category', $label('category'), PurchaseLayout::categoryOptions()),
            FilterField::text('purchase_number', $label('purchase_number')),
            FilterField::text('order_id', $label('order_id'))->query(self::filterOrderNumber(...)),
            FilterField::text('product_name', $label('product_name')),
            FilterField::number('quantity', $label('quantity')),
            FilterField::number('unit_price', $label('unit_price')),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), array_slice(self::AMOUNTS, 0, 2)),
            FilterField::choice('is_fsc', $label('is_fsc'), PurchaseChoices::options([PurchaseChoices::FSC_CODE]))
                ->query(fn (Builder $query, FilterCondition $condition) => self::filterFlag($query, 'is_fsc', PurchaseChoices::FSC_CODE, $condition)),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), array_slice(self::AMOUNTS, 2, 3)),
            FilterField::choice('period_end', $label('period_end'), PurchaseChoices::options([PurchaseChoices::PERIOD_END]))
                ->query(fn (Builder $query, FilterCondition $condition) => self::filterFlag($query, 'period_end', PurchaseChoices::PERIOD_END, $condition)),
            FilterField::choice('overruns', $label('overruns'), PurchaseChoices::options(PurchaseChoices::OVERRUNS))->query(self::filterOverruns(...)),
            FilterField::user('notified_users', $label('notified_users'))->relationship('notifiedUsers', 'id'),
            FilterField::number('total', $label('total')),
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
            InlineField::date('purchased_on')->required(),
            InlineField::select('category', PurchaseLayout::categoryOptions()),
            InlineField::text('product_name')->maxLength(255),
            InlineField::number('amount')->required()->minValue(-99999999999)->maxValue(99999999999),
            ...array_map(fn (string $name): InlineField => InlineField::number($name)->minValue(-99999999999)->maxValue(99999999999),
                ['cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount']),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(PurchaseLayout::label($name))->placeholder('');
        $supplier = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'purchases.supplier_id'),
            $direction,
        );

        return match (true) {
            in_array($name, ['purchase_number', 'product_name'], true) => $column->searchable(),
            $name === 'purchased_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'quantity' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'unit_price' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            in_array($name, self::AMOUNTS, true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'supplier_id' => $column
                ->state(fn (Purchase $record): string => $record->supplier->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('supplier', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $supplier('name')),
            $name === 'order_id' => $column
                ->state(fn (Purchase $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Order::query()->select('orders.order_number')->whereColumn('orders.id', 'purchases.order_id'),
                    $direction,
                )),
            $name === 'tax_mode' => $column->state(fn (Purchase $record): ?string => AppFormat::text($record->supplier->getAttribute('supplier_tax_mode')))
                ->sortable(query: $supplier('supplier_tax_mode')),
            $name === 'master_category' => $column->state(fn (Purchase $record): ?string => AppFormat::text($record->supplier->purchase_category))
                ->sortable(query: $supplier('purchase_category')),
            $name === 'supplier_code' => $column->state(fn (Purchase $record): ?string => $record->supplier->code)->sortable(query: $supplier('code')),
            $name === 'supplier_rounding' => $column->state(fn (Purchase $record): string => $record->roundingMode()->value)
                ->sortable(query: $supplier('supplier_rounding')),
            $name === 'category' => $column->state(fn (Purchase $record): ?string => AppFormat::text($record->category)),
            $name === 'effective_category' => $column->state(fn (Purchase $record): ?string => AppFormat::text($record->effectiveCategory()))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    'coalesce(purchases.category, (select partners.purchase_category from partners where partners.id = purchases.supplier_id)) '.($direction === 'desc' ? 'desc' : 'asc'),
                )),
            $name === 'purchase_month' => $column->state(fn (Purchase $record): string => $record->month()->label())
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('purchases.purchased_on', $direction)),
            $name === 'is_fsc' => $column->state(fn (Purchase $record): ?string => $record->is_fsc ? PurchaseChoices::FSC_CODE : null),
            $name === 'period_end' => $column->state(fn (Purchase $record): ?string => $record->period_end ? PurchaseChoices::PERIOD_END : null),
            $name === 'overruns' => $column->state(fn (Purchase $record): ?string => implode('、', (array) $record->overruns) ?: null),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            $name === 'notified_users' => $column
                ->state(fn (Purchase $record): HtmlString => UserPopup::list($record->notifiedUsers))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('purchase_user', 'purchase_user.user_id', '=', 'users.id')
                        ->whereColumn('purchase_user.purchase_id', 'purchases.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }

    /**
     * 受注No is the job's 注番, compared like a text field.
     *
     * @param  Builder<*>  $query
     */
    private static function filterOrderNumber(Builder $query, FilterCondition $condition): void
    {
        $value = $condition->values[0] ?? '';
        $has = fn (callable $where): Builder => $query->whereHas('order', $where);

        match ($condition->operator) {
            FilterOperator::Contains => $has(fn (Builder $q): Builder => $q->whereLike('order_number', '%'.$value.'%')),
            FilterOperator::NotContains => $query->whereDoesntHave('order', fn (Builder $q): Builder => $q->whereLike('order_number', '%'.$value.'%')),
            FilterOperator::Equals => $value === '' ? $query->whereDoesntHave('order') : $has(fn (Builder $q): Builder => $q->where('order_number', $value)),
            FilterOperator::NotEquals => $value === '' ? $query->whereHas('order') : $query->whereDoesntHave('order', fn (Builder $q): Builder => $q->where('order_number', $value)),
            FilterOperator::OneOf => $has(fn (Builder $q): Builder => $q->whereIn('order_number', $condition->values)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * A legacy single check box stored as a boolean (FSC 「115」, 帳端処理 「〇」); "--" = unchecked.
     *
     * @param  Builder<*>  $query
     */
    private static function filterFlag(Builder $query, string $column, string $option, FilterCondition $condition): void
    {
        $checked = in_array($option, $condition->values, true);
        $blank = in_array('', $condition->values, true);
        if ($condition->operator === FilterOperator::NotIn) {
            [$checked, $blank] = [! $checked, ! $blank];
        }

        match (true) {
            $checked && $blank => null,
            $checked => $query->where("purchases.{$column}", true),
            $blank => $query->where("purchases.{$column}", false),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 仕入原価超過: any of the chosen options; "--" = none chosen.
     *
     * @param  Builder<*>  $query
     */
    private static function filterOverruns(Builder $query, FilterCondition $condition): void
    {
        $chosen = array_values(array_filter($condition->values, fn (string $value): bool => $value !== ''));
        $blank = in_array('', $condition->values, true);
        $matches = function (Builder $q) use ($chosen, $blank): void {
            foreach ($chosen as $value) {
                $q->orWhereJsonContains('purchases.overruns', $value);
            }
            if ($blank) {
                $q->orWhereNull('purchases.overruns')->orWhereJsonLength('purchases.overruns', 0);
            }
        };

        $condition->operator === FilterOperator::NotIn ? $query->whereNot($matches) : $query->where($matches);
    }
}
