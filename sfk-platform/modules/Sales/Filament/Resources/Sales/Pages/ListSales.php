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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\MenuButtons;
use Modules\Orders\Models\Order;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\Documents\InvoiceIssuer;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Filament\Resources\Sales\Tables\SalesTable;
use Modules\Sales\Models\Sale;

/**
 * List of 06. 売上入力 with 集計サマリー on top of the table (legacy 「📊 集計サマリー」 of 06_SalesEntry.bundle.js) over every
 * sale of the list as filtered and searched: 売上金額（税抜）, 見積原価合計, 見積利益 (= 売上金額 − 見積原価合計), 消費税,
 * 売上金額（税込）, 実原価合計 of the sales' jobs, 粗利 (= 税込 − 実原価合計) and 粗利率 (粗利 / 税込), with 対象件数, 売上数量
 * and 売上残数量 (of the jobs). Administrators have the legacy list buttons and their 機能説明 (menuActions(), menuHelp()).
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

    /**
     * The legacy list buttons, for administrators (Customine 06 pages 1 / 6 / 9 / 13 / 18): 期間指定から納品レコード作成 and,
     * on the checked rows, 選択して納品書レコード作成, 選択して請求書レコード作成 (optional 請求日), 随時請求書レコード作成 and
     * 編集制限 (編集不可にする / 編集可能にする). The issuers refuse what the legacy flow refused (rows already on a
     * document, 随時請求 rows on a closing-day invoice, rows without a delivery note).
     */
    protected function menuActions(): array
    {
        return [
            Action::make('issueDeliveryNotesForPeriod')
                ->label('期間指定から納品レコード作成')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->modalHeading('期間指定から納品レコード作成')
                ->modalWidth('md')
                ->modalSubmitActionLabel('作成する')
                ->modalCancelActionLabel('キャンセル')
                ->schema([
                    DatePicker::make('from')->label('開始日')->required(),
                    DatePicker::make('to')->label('終了日')->required()->afterOrEqual('from'),
                    Select::make('closing_day')->label('締め日')->placeholder('-----')->native(false)->required()
                        ->options([...self::closingDayOptions(), self::ANY_CLOSING_DAY => self::ANY_CLOSING_DAY]),
                ])
                ->action(fn (array $data) => self::run('納品書レコードの作成が完了しました', fn (): array => app(DeliveryNoteIssuer::class)->issueForPeriod(
                    CarbonImmutable::parse((string) $data['from']),
                    CarbonImmutable::parse((string) $data['to']),
                    ClosingDay::tryFrom((string) ($data['closing_day'] ?? '')),
                ))),
            MenuButtons::withCheckedCount(self::onCheckedRows(Action::make('createDeliveryNotes')))
                ->label('選択して納品書レコード作成')
                ->icon(Heroicon::OutlinedDocumentText)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('選択された売上レコードから得意先別に納品書レコードを作成しますか？')
                ->modalSubmitActionLabel('作成する')
                ->action(fn (Collection $records) => self::onRecords($records, '納品書レコードの作成が完了しました',
                    fn (Collection $sales): array => app(DeliveryNoteIssuer::class)->issue($sales)))
                ->deselectRecordsAfterCompletion(),
            MenuButtons::withCheckedCount(self::onCheckedRows(Action::make('createInvoices')))
                ->label('選択して請求書レコード作成')
                ->icon(Heroicon::OutlinedDocumentCurrencyYen)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->modalHeading('選択された売上レコードから得意先別に請求書レコードを作成しますか？')
                ->modalWidth('md')
                ->modalSubmitActionLabel('作成する')
                ->schema([DatePicker::make('invoiced_on')->label('請求日')])
                ->action(fn (Collection $records, array $data) => self::onRecords($records, '請求書レコードの作成が完了しました',
                    fn (Collection $sales): array => app(InvoiceIssuer::class)->issue($sales, filled($data['invoiced_on'] ?? null) ? CarbonImmutable::parse((string) $data['invoiced_on']) : null)))
                ->deselectRecordsAfterCompletion(),
            self::onCheckedRows(Action::make('createAdHocInvoices'))
                ->label('随時請求書レコード作成')
                ->icon(Heroicon::OutlinedBolt)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('選択された売上レコードから随時請求に該当するレコードのみ得意先別に請求書レコードを作成しますか？')
                ->modalSubmitActionLabel('作成する')
                ->action(fn (Collection $records) => self::onRecords($records, '請求書レコードの作成が完了しました',
                    fn (Collection $sales): array => app(InvoiceIssuer::class)->issueAdHoc($sales)))
                ->deselectRecordsAfterCompletion(),
            self::onCheckedRows(Action::make('restrictEditing'))
                ->label('編集制限')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->modalHeading('選択されたレコードに対して編集制限操作します。')
                ->modalWidth('md')
                ->modalSubmitActionLabel('OK')
                ->modalCancelActionLabel('キャンセル')
                ->schema([
                    Select::make('operation')->hiddenLabel()->placeholder('-----')->native(false)->required()
                        ->options([self::LOCK => self::LOCK, self::UNLOCK => self::UNLOCK]),
                ])
                ->action(function (Collection $records, array $data): void {
                    if ($records->isEmpty()) {
                        Notification::make()->warning()->title('レコードを選択してください。')->send();

                        return;
                    }

                    Sale::query()->whereKey($records->modelKeys())->update(['locked' => $data['operation'] === self::LOCK]);
                    Notification::make()->success()->title($data['operation'] === self::LOCK ? '編集を不可にしました' : '編集を可能にしました')->send();
                })
                ->deselectRecordsAfterCompletion(),
            ...$this->scriptActions(),
        ];
    }

    /**
     * The list buttons of 06_SalesEntry.bundle.js, for administrators, on the checked rows: 【新】選択して納品書レコード作成 and
     * 【新】選択して請求書レコード作成 — per customer onto the document one of the sales is already on, else a new one
     * (issueOrAppend()) — and 削除: the sales deleted after two confirmations, their rows going from the delivery notes and
     * invoices with them (Sale::deleting).
     *
     * @return list<Action>
     */
    private function scriptActions(): array
    {
        return [
            MenuButtons::withCheckedCount(self::onCheckedRows(Action::make('appendDeliveryNotes')))
                ->label('【新】選択して納品書レコード作成')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading(fn (Action $action): string => '選択された'.$action->getSelectedRecords()->count().'件の売上データから、顧客ごとに納品書データが作成されます。')
                ->modalSubmitActionLabel('OK')
                ->modalCancelActionLabel('キャンセル')
                ->action(fn (Collection $records) => self::onScriptRecords($records, '納品書レコードの作成が完了しました。', '10. 納品書管理(売上)アプリから確認してください。',
                    fn (Collection $sales): array => app(DeliveryNoteIssuer::class)->issueOrAppend($sales)))
                ->deselectRecordsAfterCompletion(),
            MenuButtons::withCheckedCount(self::onCheckedRows(Action::make('appendInvoices')))
                ->label('【新】選択して請求書レコード作成')
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible(fn (): bool => SaleForm::isAdministrator())
                ->modalHeading(fn (Action $action): string => '選択された'.$action->getSelectedRecords()->count().'件の売上データから、顧客ごとに請求書データが作成されます。')
                ->modalWidth('md')
                ->modalSubmitActionLabel('OK')
                ->modalCancelActionLabel('キャンセル')
                ->schema([DatePicker::make('invoiced_on')->label('請求日')])
                ->action(fn (Collection $records, array $data) => self::onScriptRecords($records, '請求書レコードの作成が完了しました。', '同時に入金管理アプリにも新規レコードを追加しています。詳細は各アプリから確認してください。',
                    fn (Collection $sales): array => app(InvoiceIssuer::class)->issueOrAppend($sales, filled($data['invoiced_on'] ?? null) ? CarbonImmutable::parse((string) $data['invoiced_on']) : null)))
                ->deselectRecordsAfterCompletion(),
            self::onCheckedRows(Action::make('deleteSales'))
                ->label('削除')
                ->icon(Heroicon::OutlinedTrash)
                ->visible(fn (): bool => SaleForm::isAdministrator() && SaleResource::canDeleteAny())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->modalHeading(fn (Action $action): string => '選択された'.$action->getSelectedRecords()->count().'件の売上データに関連する売上明細は、対応する納品書および請求書データから削除されます。')
                ->modalSubmitActionLabel('削除')
                ->modalCancelActionLabel('キャンセル')
                ->action(fn (Collection $records) => $this->replaceMountedAction('confirmDeleteSales', ['ids' => $records->modelKeys()])),
        ];
    }

    /** 削除, second step: 「この操作は取り消せません」, then the sales the user may delete are deleted. */
    public function confirmDeleteSalesAction(): Action
    {
        return Action::make('confirmDeleteSales')
            ->visible(fn (): bool => SaleForm::isAdministrator())
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading('確認')
            ->modalDescription('この操作は取り消せません。実行してもよろしいですか？')
            ->modalSubmitActionLabel('OK')
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $ids = array_map(intval(...), (array) ($arguments['ids'] ?? []));
                DB::transaction(function () use ($ids): void {
                    foreach (Sale::query()->whereKey($ids)->get() as $sale) {
                        if (SaleResource::canDelete($sale)) {
                            $sale->delete();
                        }
                    }
                });

                $this->deselectAllTableRecords();
                Notification::make()->success()->title('選択されたレコードの削除が完了しました。')->send();
            });
    }

    /**
     * 機能説明 of the buttons, for administrators (Customine 06 page 22).
     *
     * @return array<string, string>
     */
    public function menuHelp(): array
    {
        return SaleForm::isAdministrator() ? [
            '期間指定から納品レコード作成' => '売上日付を指定した上で任意の売上レコードを取引先別に納品レコードに変換します。',
            '選択して納品書レコード作成' => 'チェックボックスで選択したレコードを取引先別に納品レコードに変換します。',
            '選択して請求書レコード作成' => 'チェックボックスで選択したレコードを取引先別に請求レコードに変換します。（納品レコードが作成されてない場合実行不可）',
            '編集制限' => '任意のタイミングで任意のレコードに対して編集制限をかけることができます。（解除も可能）',
        ] : [];
    }

    private const ANY_CLOSING_DAY = '指定なし';

    private const LOCK = '編集不可にする';

    private const UNLOCK = '編集可能にする';

    /** @return array<string, string> 締め日 of the legacy dialog: the fixed closing days and 末日 */
    private static function closingDayOptions(): array
    {
        $days = array_filter(ClosingDay::cases(), fn (ClosingDay $day): bool => $day !== ClosingDay::AdHoc);

        return array_combine(array_map(fn (ClosingDay $day): string => $day->value, $days), array_map(fn (ClosingDay $day): string => $day->value, $days));
    }

    /**
     * Runs a document creation on the checked sales; nothing checked → 「レコードを選択してください。」.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Collection<int, Sale>): array<mixed>  $create
     */
    private static function onRecords(Collection $records, string $done, callable $create): void
    {
        if ($records->isEmpty()) {
            Notification::make()->warning()->title('レコードを選択してください。')->send();

            return;
        }

        self::run($done, fn (): array => $create($records->whereInstanceOf(Sale::class)->values()));
    }

    /**
     * A 【新】 button: the legacy messages of the script, 「完了」 with where to look, else why it was refused.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Collection<int, Sale>): array<mixed>  $create
     */
    private static function onScriptRecords(Collection $records, string $done, string $where, callable $create): void
    {
        if ($records->isEmpty()) {
            Notification::make()->warning()->title('レコードを選択してください。')->send();

            return;
        }

        try {
            $create($records->whereInstanceOf(Sale::class)->values());
            Notification::make()->success()->title($done)->body($where)->send();
        } catch (SalesActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->persistent()->send();
        }
    }

    /** @param  callable(): array<mixed>  $create */
    private static function run(string $done, callable $create): void
    {
        try {
            $documents = $create();
            Notification::make()->success()->title($done.'（'.count($documents).'件）')->send();
        } catch (SalesActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->persistent()->send();
        }
    }
}
