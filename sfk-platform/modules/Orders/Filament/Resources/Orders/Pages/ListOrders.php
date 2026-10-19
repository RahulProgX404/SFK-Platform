<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Orders\Domain\Orders\JobCosting;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use Modules\Orders\Models\Order;

/**
 * List of 04. 受注入力 with the legacy 集計サマリー on top of the table (OrderEntry.bundle.js list widget): the sums of 売上金額,
 * 見積原価合計, 見積利益, 実売上金額, 社内原価, 仕入原価計, 実原価合計 and the margin (実売上金額 − 実原価合計) / 実売上金額
 * over every record of the list as filtered and searched (not only the page shown), with 対象件数.
 */
final class ListOrders extends AppListRecords
{
    protected static string $resource = OrderResource::class;

    /** Summed columns of 集計サマリー, in legacy order. */
    public const SUMMARY_COLUMNS = [
        'sales_amount', 'estimated_cost_total', 'estimated_profit', 'actual_sales_amount', 'internal_cost', 'purchase_cost_total', 'actual_cost_total',
    ];

    public function listColumns(): array
    {
        return OrdersTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_04 views.md). */
    public function defaultListViews(): array
    {
        return OrdersTable::defaultViews();
    }

    public function filterFields(): array
    {
        return OrdersTable::filterFields();
    }

    public function inlineFields(): array
    {
        return OrdersTable::inlineFields();
    }

    /**
     * 集計サマリー directly under the search row, inside the table box so summary and table stay together (render hook
     * of the table toolbar, OrdersServiceProvider); empty elsewhere and for calendar views.
     */
    public static function summaryBar(): string
    {
        $page = Livewire::current();

        return $page instanceof self && ! $page->currentView()->isCalendar()
            ? view('orders::filament.order-summary', ['page' => $page])->render()
            : '';
    }

    /**
     * 集計サマリー of the list as filtered and searched.
     *
     * @return array{count: int, sums: array<string, int>, margin: string|null}
     */
    public function orderSummary(): array
    {
        $query = $this->getFilteredTableQuery();
        $sums = array_fill_keys(self::SUMMARY_COLUMNS, 0);
        $count = 0;

        if ($query instanceof Builder) {
            $row = $query->toBase()
                ->reorder()
                ->selectRaw('count(*) as aggregate_count, '.implode(', ', array_map(
                    fn (string $column): string => "coalesce(sum({$query->getModel()->qualifyColumn($column)}), 0) as {$column}",
                    self::SUMMARY_COLUMNS,
                )))
                ->first();

            if ($row !== null) {
                $count = (int) $row->aggregate_count;
                foreach (self::SUMMARY_COLUMNS as $column) {
                    $sums[$column] = (int) $row->{$column};
                }
            }
        }

        return ['count' => $count, 'sums' => $sums, 'margin' => JobCosting::summaryMargin($sums['actual_sales_amount'], $sums['actual_cost_total'])];
    }

    /** 担当者 as user ids. */
    protected function inlineEditState(Model $record): array
    {
        $state = parent::inlineEditState($record);

        if ($record instanceof Order) {
            $state['assignees'] = $record->assignees()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
        }

        return $state;
    }

    /** 担当者 in order_assignees; the calculated columns follow (Order). */
    protected function saveInlineRecord(Model $record, array $data): void
    {
        $assignees = array_map('intval', (array) ($data['assignees'] ?? []));
        unset($data['assignees']);

        $record->fill($data)->save();

        if ($record instanceof Order) {
            $record->assignees()->sync($assignees);
        }
    }
}
