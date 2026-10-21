<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\FreightCharges\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Application\Freight\FreightPurchasePoster;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeLayout;
use Modules\Purchasing\Filament\Resources\FreightCharges\FreightChargeResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseLayout;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\FreightCharge;
use Modules\Purchasing\Models\Purchase;

/**
 * Record list of 15. 運送請求データ取込 with the legacy view 運送請求データ一覧 (SYSTEM_BRAIN APP_15 views.md: 日付, 注番, 金額,
 * 運送会社番号, 備考, newest first); （すべて） shows columns() in order and the search box replaces the 検索特化 view.
 * Selected rows go to 05 with 選択して納品(仕入)を作成 (FreightPurchasePoster) for users who may add purchases.
 * インライン編集 changes 日付, 金額, 運送会社番号 and 備考.
 */
final class FreightChargesTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['charged_on', 'order_id', 'amount', 'carrier_number', 'notes'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'charged_on', 'order_id', 'amount', 'carrier_number', 'notes', 'unit_price', 'quantity', 'cutting_amount', 'plate_amount',
            'stored_plate_amount', 'spot_color_amount', 'purchase_id', 'created_at'] as $name) {
            $columns[$name] = FreightChargeLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '運送請求データ一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['order', 'purchase']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(FreightChargeResource::class))
            ->toolbarActions([BulkActionGroup::make([self::postAction()])])
            ->emptyStateIcon(Heroicon::OutlinedDocumentArrowUp)
            ->emptyStateHeading('運送請求データがありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_15 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => FreightChargeLayout::label($name);

        return [
            FilterField::date('charged_on', $label('charged_on')),
            FilterField::text('order_id', $label('order_id'))->query(fn (Builder $query, FilterCondition $condition) => self::filterNumber($query, 'order', 'order_number', $condition)),
            FilterField::number('amount', $label('amount')),
            FilterField::text('carrier_number', $label('carrier_number')),
            FilterField::text('purchase_id', $label('purchase_id'))->query(fn (Builder $query, FilterCondition $condition) => self::filterNumber($query, 'purchase', 'purchase_number', $condition)),
            FilterField::number('unit_price', $label('unit_price')),
            FilterField::number('quantity', $label('quantity')),
            ...array_map(fn (string $name): FilterField => FilterField::number($name, $label($name)), array_slice(FreightChargeLayout::AMOUNTS, 1)),
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
            InlineField::date('charged_on'),
            InlineField::number('amount')->minValue(-99999999999)->maxValue(99999999999),
            InlineField::text('carrier_number')->maxLength(64),
            InlineField::text('notes')->maxLength(255),
        ];
    }

    private static function postAction(): BulkAction
    {
        return BulkAction::make('postPurchases')
            ->label('選択して納品(仕入)を作成')
            ->icon(Heroicon::OutlinedTruck)
            ->visible(fn (): bool => PurchaseResource::canCreate())
            ->modalHeading('選択した運送請求データから納品(仕入)を作成')
            ->modalSubmitActionLabel('作成する')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                Select::make('supplier_id')
                    ->label(PurchaseLayout::label('supplier_id'))
                    ->options(fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->placeholder('-----')
                    ->required(),
                Select::make('category')->label(PurchaseLayout::label('category'))->placeholder('-----')->options(PurchaseLayout::categoryOptions())->searchable(),
            ])
            ->action(function (Collection $records, array $data): void {
                $carrier = Partner::query()->find((int) ($data['supplier_id'] ?? 0));
                if ($carrier === null) {
                    return;
                }

                try {
                    $purchases = app(FreightPurchasePoster::class)->post(
                        $records->whereInstanceOf(FreightCharge::class),
                        $carrier,
                        PurchaseCategory::tryFrom((string) ($data['category'] ?? '')),
                    );
                } catch (PurchasingActionNotAllowed $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('納品(仕入)を作成しました（'.count($purchases).'件）')->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(FreightChargeLayout::label($name))->placeholder('');

        return match (true) {
            in_array($name, ['carrier_number', 'notes'], true) => $column->searchable(),
            $name === 'charged_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            in_array($name, FreightChargeLayout::AMOUNTS, true) => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'unit_price' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::price($state)),
            $name === 'quantity' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::number($state)),
            $name === 'order_id' => $column
                ->state(fn (FreightCharge $record): ?string => $record->order?->order_number)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $q): Builder => $q->whereLike('order_number', "%{$search}%")))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Order::query()->select('orders.order_number')->whereColumn('orders.id', 'freight_charges.order_id'),
                    $direction,
                )),
            $name === 'purchase_id' => $column
                ->state(fn (FreightCharge $record): ?string => $record->purchase?->purchase_number)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Purchase::query()->select('purchases.purchase_number')->whereColumn('purchases.id', 'freight_charges.purchase_id'),
                    $direction,
                )),
            default => $column,
        };
    }

    /**
     * 注番 / 納品(仕入)入力No are numbers of related records, compared like a text field.
     *
     * @param  Builder<*>  $query
     */
    private static function filterNumber(Builder $query, string $relation, string $column, FilterCondition $condition): void
    {
        $value = $condition->values[0] ?? '';
        $has = fn (callable $where): Builder => $query->whereHas($relation, $where);

        match ($condition->operator) {
            FilterOperator::Contains => $has(fn (Builder $q): Builder => $q->whereLike($column, '%'.$value.'%')),
            FilterOperator::NotContains => $query->whereDoesntHave($relation, fn (Builder $q): Builder => $q->whereLike($column, '%'.$value.'%')),
            FilterOperator::Equals => $value === '' ? $query->whereDoesntHave($relation) : $has(fn (Builder $q): Builder => $q->where($column, $value)),
            FilterOperator::NotEquals => $value === '' ? $query->whereHas($relation) : $query->whereDoesntHave($relation, fn (Builder $q): Builder => $q->where($column, $value)),
            FilterOperator::OneOf => $has(fn (Builder $q): Builder => $q->whereIn($column, $condition->values)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
