<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderLayout;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Models\ConsumableOrder;
use Modules\Purchasing\Models\ConsumableOrderLine;

/**
 * Record list of 20. 消耗品発注 with the legacy views (SYSTEM_BRAIN APP_20 views.md): 発注一覧 (発注者, 発注日, 発注先,
 * 発注明細, newest first) and （作業者が自分） (the orders waiting for the signed-in user: own 未処理 orders, and every
 * 確認中 order for administrators); （すべて） shows columns() in order and the search box replaces 発注一覧(検索特化).
 * インライン編集 changes 発注日 and 備考.
 */
final class ConsumableOrdersTable
{
    public const VIEW_LIST = 'list';

    public const VIEW_MINE = 'assigned';

    public const LIST_COLUMNS = ['orderers', 'ordered_on', 'supplier_id', 'lines'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (self::allNames() as $name) {
            $columns[$name] = ConsumableOrderLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $byId = [['field' => 'id', 'direction' => 'desc']];

        return [
            ListView::table(self::VIEW_LIST, '発注一覧', self::LIST_COLUMNS, $byId),
            ListView::table(self::VIEW_MINE, '（作業者が自分）', array_values(array_diff(self::allNames(), ['lines', 'created_at'])), $byId,
                [['field' => 'assignee', 'operator' => FilterOperator::In->value, 'values' => [FilterField::LOGIN_USER]]]),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supplier', 'orderers', 'creator', 'lines.consumable']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(ConsumableOrderResource::class))
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart)
            ->emptyStateHeading('発注がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_20 fields.md); the row fields
     * match orders with any such row.
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => ConsumableOrderLayout::label($name);

        return [
            FilterField::date('ordered_on', $label('ordered_on')),
            FilterField::choice('supplier_id', $label('supplier_id'), fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::user('orderers', $label('orderers'))->relationship('orderers', 'id'),
            FilterField::choice('consumable_id', $label('consumable_id'), fn (): array => Consumable::query()->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all())
                ->relationship('lines', 'consumable_id'),
            FilterField::text('orderer', $label('orderer'))->relationship('lines', 'orderer'),
            FilterField::text('line_notes', $label('line_notes'))->relationship('lines', 'notes'),
            FilterField::textarea('notes', $label('notes')),
            FilterField::choice('status', $label('status'), ConsumableOrderStatus::options()),
            FilterField::user('assignee', $label('assignee'))->query(self::filterAssignee(...)),
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
        return [InlineField::date('ordered_on')->required(), InlineField::textarea('notes')];
    }

    /** @return list<string> */
    private static function allNames(): array
    {
        return ['id', 'orderers', 'ordered_on', 'supplier_id', ...array_keys(ConsumableOrderLayout::SUPPLIER_VALUES), 'notes', 'status', 'assignee', 'lines',
            'created_at'];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(ConsumableOrderLayout::label($name))->placeholder('');
        $supplier = fn (string $attribute): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'consumable_orders.supplier_id'),
            $direction,
        );

        return match (true) {
            $name === 'notes' => $column->searchable(),
            $name === 'ordered_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            $name === 'status' => $column->state(fn (ConsumableOrder $record): string => $record->status->value),
            $name === 'supplier_id' => $column
                ->state(fn (ConsumableOrder $record): ?string => $record->supplier?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('supplier', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $supplier('name')),
            isset(ConsumableOrderLayout::SUPPLIER_VALUES[$name]) => $column
                ->state(fn (ConsumableOrder $record): ?string => AppFormat::text($record->supplier?->getAttribute(ConsumableOrderLayout::SUPPLIER_VALUES[$name])))
                ->sortable(query: $supplier(ConsumableOrderLayout::SUPPLIER_VALUES[$name])),
            $name === 'lines' => $column
                ->state(fn (ConsumableOrder $record): array => $record->lines
                    ->map(fn (ConsumableOrderLine $line): string => trim(($line->consumable->name ?? '').' '.AppFormat::number($line->quantity).($line->unit ?? '')))
                    ->all())
                ->listWithLineBreaks()
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('lines.consumable', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                // by the 商品名 of the first row
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Consumable::query()->select('consumables.name')->join('consumable_order_lines', 'consumable_order_lines.consumable_id', '=', 'consumables.id')
                        ->whereColumn('consumable_order_lines.consumable_order_id', 'consumable_orders.id')
                        ->orderBy('consumable_order_lines.sort')->orderBy('consumable_order_lines.id')->limit(1),
                    $direction,
                )),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            $name === 'orderers' => $column
                ->state(fn (ConsumableOrder $record): HtmlString => UserPopup::list($record->orderers))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('consumable_order_user', 'consumable_order_user.user_id', '=', 'users.id')
                        ->whereColumn('consumable_order_user.consumable_order_id', 'consumable_orders.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            $name === 'assignee' => $column
                ->state(fn (ConsumableOrder $record): HtmlString => ConsumableOrderLayout::assignee($record))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('consumable_orders.status', $direction)
                    ->orderBy(User::query()->select('users.name')->whereColumn('users.id', 'consumable_orders.created_by'), $direction)),
            default => $column,
        };
    }

    /**
     * 作業者 in (users): the orders waiting for them — their own 未処理 orders, and the 確認中 ones when an administrator
     * is among them (ConsumableOrderWorkflow).
     *
     * @param  Builder<*>  $query
     */
    private static function filterAssignee(Builder $query, FilterCondition $condition): void
    {
        $ids = array_map(fn (string $value): int => $value === FilterField::LOGIN_USER ? (int) auth()->id() : (int) $value,
            array_values(array_filter($condition->values, fn (string $value): bool => $value !== '')));
        $blank = in_array('', $condition->values, true);
        $administrator = User::query()->whereKey($ids)->whereIn('user_type', [UserType::Admin->value, UserType::SuperAdmin->value])->exists();

        $matches = function (Builder $q) use ($ids, $blank, $administrator): void {
            $q->where(fn (Builder $own): Builder => $own->where('consumable_orders.status', ConsumableOrderStatus::Unprocessed->value)
                ->whereIn('consumable_orders.created_by', $ids === [] ? [0] : $ids));
            if ($administrator) {
                $q->orWhere('consumable_orders.status', ConsumableOrderStatus::Checking->value);
            }
            if ($blank) {
                $q->orWhere('consumable_orders.status', ConsumableOrderStatus::Approved->value);
            }
        };

        $condition->operator === FilterOperator::NotIn ? $query->whereNot($matches) : $query->where($matches);
    }
}
