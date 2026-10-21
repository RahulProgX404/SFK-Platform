<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Orders\Models\Order;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Filament\Resources\Sales\Tables\SalesTable;

/**
 * List of 06. 売上入力 with 集計サマリー on top of the table (legacy 「📊 集計サマリー」 of 06_SalesEntry.bundle.js) over every
 * sale of the list as filtered and searched: 売上金額（税抜）, 見積原価合計, 見積利益 (= 売上金額 − 見積原価合計), 消費税,
 * 売上金額（税込）, 実原価合計 of the sales' jobs, 粗利 (= 税込 − 実原価合計) and 粗利率 (粗利 / 税込), with 対象件数, 売上数量
 * and 売上残数量 (of the jobs). 「…」 has 期間を指定して納品書を作成 for administrators (Customine 06 page 1).
 */
final class ListSales extends AppListRecords
{
    protected static string $resource = SaleResource::class;

    public function listColumns(): array
    {
        return SalesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_06 views.md). */
    public function defaultListViews(): array
    {
        return SalesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return SalesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return SalesTable::inlineFields();
    }

    /** 集計サマリー under the search row (render hook of the table toolbar, SalesServiceProvider); not for calendar views. */
    public static function summaryBar(): string
    {
        $page = Livewire::current();

        return $page instanceof self && ! $page->currentView()->isCalendar()
            ? view('sales::filament.sales-summary', ['page' => $page])->render()
            : '';
    }

    /**
     * @return array{count: int, quantity: string, remaining: string, rows: list<array{0: string, 1: string, 2: int|float|null}>}
     */
    public function salesSummary(): array
    {
        $query = $this->getFilteredTableQuery();
        $sums = ['count' => 0, 'quantity' => '0', 'amount' => 0, 'estimated_cost' => 0, 'tax' => 0];
        $orders = collect();

        if ($query instanceof Builder) {
            $row = $query->toBase()->reorder()
                ->selectRaw('count(*) as aggregate_count, coalesce(sum(sales.quantity), 0) as quantity, coalesce(sum(sales.amount), 0) as amount, '
                    .'coalesce(sum(sales.estimated_cost), 0) as estimated_cost, coalesce(sum(sales.tax), 0) as tax')
                ->first();
            if ($row !== null) {
                $sums = ['count' => (int) $row->aggregate_count, 'quantity' => (string) $row->quantity, 'amount' => (int) $row->amount,
                    'estimated_cost' => (int) $row->estimated_cost, 'tax' => (int) $row->tax];
            }
            $orders = Order::query()->whereIn('id', $query->clone()->toBase()->reorder()->whereNotNull('sales.order_id')->select('sales.order_id'))->get(['id', 'actual_cost_total', 'sales_remaining']);
        }

        $withTax = $sums['amount'] + $sums['tax'];
        $actualCost = (int) $orders->sum('actual_cost_total');
        $gross = $withTax - $actualCost;

        return [
            'count' => $sums['count'],
            'quantity' => $sums['quantity'],
            'remaining' => (string) $orders->sum(fn (Order $order): float => (float) $order->getAttribute('sales_remaining')),
            'rows' => [
                ['売上金額（税抜）', 'yen', $sums['amount']],
                ['見積原価合計', 'yen', $sums['estimated_cost']],
                ['見積利益', 'signed', $sums['amount'] - $sums['estimated_cost']],
                ['消費税', 'yen', $sums['tax']],
                ['売上金額（税込）', 'yen', $withTax],
                ['実原価合計', 'yen', $actualCost],
                ['粗利', 'signed', $gross],
                ['粗利率', 'percent', $withTax !== 0 ? round($gross / $withTax * 100, 1) : null],
            ],
        ];
    }

    protected function optionActions(): array
    {
        return [
            Action::make('issueDeliveryNotesForPeriod')
                ->label('期間を指定して納品書を作成')
                ->icon(Heroicon::OutlinedDocumentText)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->modalHeading('期間を指定して納品書を作成')
                ->modalWidth('md')
                ->modalSubmitActionLabel('作成する')
                ->modalCancelActionLabel('キャンセル')
                ->schema([
                    DatePicker::make('from')->label('開始日')->required(),
                    DatePicker::make('to')->label('終了日')->required()->afterOrEqual('from'),
                    Select::make('closing_day')->label('締日')->placeholder('指定なし')->native(false)->options(array_combine(
                        array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
                        array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
                    )),
                ])
                ->action(function (array $data): void {
                    try {
                        $notes = app(DeliveryNoteIssuer::class)->issueForPeriod(
                            CarbonImmutable::parse((string) $data['from']),
                            CarbonImmutable::parse((string) $data['to']),
                            ClosingDay::tryFrom((string) ($data['closing_day'] ?? '')),
                        );
                        Notification::make()->success()->title('納品書を作成しました（'.count($notes).'件）')->send();
                    } catch (SalesActionNotAllowed $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
