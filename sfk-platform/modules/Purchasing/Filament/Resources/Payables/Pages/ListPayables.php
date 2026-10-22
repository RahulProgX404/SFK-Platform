<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Pages;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Application\Payables\PayableLedger;
use Modules\Purchasing\Filament\Resources\Payables\PayableLayout;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Filament\Resources\Payables\Tables\PayablesTable;
use Modules\Purchasing\Models\PayableMonth;

/**
 * List of 23. 買掛管理 with 合計 on top of the table (legacy Customine 23買掛 page 1: the sums of the ledger columns of
 * the list as filtered and searched). The legacy list buttons for administrators: 一括更新 (legacy 「[新]一括更新」 of
 * 23_AccountPayableManagement.bundle.js): the payables of the suppliers in the list are recalculated — needed only after
 * a supplier's 消費税区分 / 端数区分 changed, as purchases and payments recalculate them on every save — and
 * 当月未作成一括作成 / 前月未作成一括作成 (Customine 23買掛 pages 9 / 10): the month's missing records of every supplier
 * with a ledger, for the months nothing was saved in.
 */
final class ListPayables extends AppListRecords
{
    protected static string $resource = PayableResource::class;

    /** The sums shown, in order. */
    private const TOTALS = ['previous_balance', 'paid', 'net_balance', 'purchase_amount', 'tax_amount', 'month_total', 'closing_balance'];

    public function listColumns(): array
    {
        return PayablesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_23B views.md). */
    public function defaultListViews(): array
    {
        return PayablesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return PayablesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return PayablesTable::inlineFields();
    }

    /** 合計 under the search row (render hook of the table toolbar, PurchasingServiceProvider); not for calendar views. */
    public static function totalsBar(): string
    {
        $page = Livewire::current();

        return $page instanceof self && ! $page->currentView()->isCalendar()
            ? view('purchasing::filament.payable-totals', ['page' => $page])->render()
            : '';
    }

    /** @return array{count: int, rows: list<array{0: string, 1: int}>} */
    public function payableTotals(): array
    {
        $query = $this->getFilteredTableQuery();
        $sums = array_fill_keys(self::TOTALS, 0);
        $count = 0;

        if ($query instanceof Builder) {
            $row = $query->toBase()->reorder()
                ->selectRaw(implode(', ', ['count(*) as aggregate_count', ...array_map(fn (string $column): string => "coalesce(sum(payable_months.{$column}), 0) as {$column}", self::TOTALS)]))
                ->first();
            if ($row !== null) {
                $count = (int) $row->aggregate_count;
                foreach (self::TOTALS as $column) {
                    $sums[$column] = (int) $row->{$column};
                }
            }
        }

        return [
            'count' => $count,
            'rows' => array_map(fn (string $column): array => [PayableLayout::label($column), $sums[$column]], self::TOTALS),
        ];
    }

    protected function menuActions(): array
    {
        return [
            Action::make('refreshPayables')
                ->label('一括更新')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->user_type->isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('一覧の買掛を最新の状態に更新しますか？')
                ->modalSubmitActionLabel('更新する')
                ->modalCancelActionLabel('キャンセル')
                ->action(function (): void {
                    $query = $this->getFilteredTableQuery();
                    $suppliers = $query instanceof Builder ? $query->toBase()->reorder()->distinct()->pluck('payable_months.supplier_id')->all() : [];
                    $ledger = app(PayableLedger::class);
                    foreach ($suppliers as $supplierId) {
                        $ledger->refresh((int) $supplierId);
                    }

                    Notification::make()->success()->title('買掛を更新しました（'.count($suppliers).'仕入先）')->send();
                }),
            $this->createMonthAction('createCurrentMonth', '当月未作成一括作成', YearMonth::fromDate(CarbonImmutable::now())),
            $this->createMonthAction('createPreviousMonth', '前月未作成一括作成', YearMonth::fromDate(CarbonImmutable::now()->startOfMonth()->subMonth())),
        ];
    }

    /** 当月 / 前月未作成一括作成: the missing records of the month, for every supplier with a ledger. */
    private function createMonthAction(string $name, string $label, YearMonth $month): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(str_starts_with($name, 'createCurrent') ? Heroicon::OutlinedCalendar : Heroicon::OutlinedBackward)
            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->user_type->isAdministrator())
            ->requiresConfirmation()
            ->modalHeading($month->label().' の買掛レコード未作成分を一括作成しますか？')
            ->modalSubmitActionLabel('作成する')
            ->modalCancelActionLabel('キャンセル')
            ->action(function () use ($month): void {
                $before = PayableMonth::query()->where('year_month', $month->key())->count();
                $ledger = app(PayableLedger::class);
                foreach (PayableMonth::query()->distinct()->pluck('supplier_id') as $supplierId) {
                    $ledger->refresh((int) $supplierId);
                }

                $created = PayableMonth::query()->where('year_month', $month->key())->count() - $before;
                Notification::make()->success()->title('処理が完了しました（'.$month->label().'：'.max(0, $created).'件作成）')->send();
            });
    }
}
