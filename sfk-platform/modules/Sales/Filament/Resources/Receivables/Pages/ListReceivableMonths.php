<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\ReceivableLayout;
use Modules\Sales\Filament\Resources\Receivables\ReceivableMonthResource;
use Modules\Sales\Filament\Resources\Receivables\Tables\ReceivablesTable;
use Modules\Sales\Models\ReceivableMonth;

/**
 * The record list of 23. 売掛管理 / 23-2. 売掛管理(締日別), with the legacy Customine list actions:
 *
 * - 合計 under the search row: the sums of the ledger columns of the list as filtered and searched (23 page 1, 23-2 page 3);
 * - the legacy list buttons for administrators: 一括更新 — the months of the customers in the list recalculated (23 page 6, 23-2 page 8
 *   and 「[新]一括更新」 of 23-2_AccountsReceivableManagement.bundle.js, one button: refused on an empty list and, on 23-2, on
 *   the view 売掛一覧 (too many records)) —
 *   and 当月未作成一括登録 / 前月未作成一括登録 — the month's missing records created for every customer with a ledger
 *   (23 pages 4 / 5, 23-2 pages 6 / 7 for the customers of a chosen 締日). The ledger creates the months while a balance is
 *   open (BR-AR-05) whenever a sale or receipt is saved; these actions catch up the months nothing was saved in;
 * - 基準月 (`?base=YYYY-MM`, default the current month) above the table, with ‹ › and 当月: the month lists (対象月 当月 / 前月 /
 *   前々月) are relative to it, and the heading names the month shown — 「先月売掛一覧（2026年10月）」 (defect 7).
 */
abstract class ListReceivableMonths extends AppListRecords
{
    /** 基準月 (YYYY-MM); blank = the current month */
    #[Url(as: 'base')]
    public string $baseMonth = '';

    /** The sums shown, in order. */
    private const TOTALS = ['previous_balance', 'received', 'net_balance', 'sales_amount', 'tax_amount', 'month_total', 'closing_balance'];

    public function listColumns(): array
    {
        return ReceivablesTable::columns($this->basis());
    }

    /** The legacy views (SYSTEM_BRAIN views.md of the app). */
    public function defaultListViews(): array
    {
        return ReceivablesTable::defaultViews($this->basis());
    }

    public function filterFields(): array
    {
        return ReceivablesTable::filterFields($this->basis(), $this->baseMonth());
    }

    /** 基準月 of the month lists: `?base=` when valid, else the current month. */
    public function baseMonth(): YearMonth
    {
        return self::isMonthKey($this->baseMonth) ? YearMonth::parse($this->baseMonth) : YearMonth::fromDate(CarbonImmutable::now());
    }

    public function isCurrentBaseMonth(): bool
    {
        return $this->baseMonth()->equals(YearMonth::fromDate(CarbonImmutable::now()));
    }

    public function setBaseMonth(string $month): void
    {
        $this->baseMonth = self::isMonthKey($month) ? $month : '';
        $this->baseMonthChanged();
    }

    public function shiftBaseMonth(int $months): void
    {
        $this->baseMonth = $this->baseMonth()->addMonths(max(-1200, min(1200, $months)))->key();
        $this->baseMonthChanged();
    }

    public function resetBaseMonth(): void
    {
        $this->baseMonth = '';
        $this->baseMonthChanged();
    }

    /** The month the list shows by its 対象月 condition (view or 適用 filter), null when it is not one month. */
    public function shownMonth(): ?YearMonth
    {
        return ReceivablesTable::shownMonth($this->activeFilter(), $this->baseMonth());
    }

    /** 「先月売掛一覧（2026年10月）」: the 一覧 with the month it shows. */
    public function listHeading(): string
    {
        $month = $this->shownMonth();

        return $this->currentView()->name.($month === null ? '' : '（'.$month->label().'）');
    }

    /** The period rule of the list's 年月 (tooltip of the heading). */
    public function basisRule(): string
    {
        return $this->basis()->rule();
    }

    /** The 基準月 bar above the table. */
    public function content(Schema $schema): Schema
    {
        $components = parent::content($schema)->getComponents(withHidden: true);

        return $schema->components([
            ViewComponent::make('sales::filament.receivables-month-bar')->visible(fn (): bool => $this->currentView()->isTable()),
            ...$components,
        ]);
    }

    private static function isMonthKey(string $value): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
    }

    private function baseMonthChanged(): void
    {
        $this->cancelInlineEdit();
        $this->resetPage();
    }

    public function inlineFields(): array
    {
        return ReceivablesTable::inlineFields();
    }

    /** 合計: the cards above the 一覧 tabs (Core), for the list as filtered and searched. */
    public function summaryCards(): array
    {
        $totals = $this->receivableTotals();

        return [
            self::summaryCard('対象件数', $totals['count'], 'count'),
            ...array_map(fn (array $row): array => self::summaryCard($row[0], $row[1]), $totals['rows']),
        ];
    }

    /** @return array{count: int, rows: list<array{0: string, 1: int}>} */
    public function receivableTotals(): array
    {
        $query = $this->getFilteredTableQuery();
        $sums = array_fill_keys(self::TOTALS, 0);
        $count = 0;

        if ($query instanceof Builder) {
            $row = $query->toBase()->reorder()
                ->selectRaw(implode(', ', ['count(*) as aggregate_count', ...array_map(fn (string $column): string => "coalesce(sum(receivable_months.{$column}), 0) as {$column}", self::TOTALS)]))
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
            'rows' => array_map(fn (string $column): array => [ReceivableLayout::label($column, $this->basis()), $sums[$column]], self::TOTALS),
        ];
    }

    protected function menuActions(): array
    {
        return [
            Action::make('refreshReceivables')
                ->label('一括更新')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => $this->isAppAdministrator())
                ->mountUsing(function (): void {
                    if ($this->basis() === ReceivableBasis::Cycle && $this->currentView()->name === '売掛一覧') {
                        Notification::make()->warning()->title('売掛一覧はレコード数が多いため一覧条件を変更の上実行してください。')->send();

                        throw new Cancel;
                    }
                    if ($this->listedReceivableCount() === 0) {
                        Notification::make()->warning()->title('処理可能なレコードが存在しません。一覧の条件設定を確認してください。')->send();

                        throw new Cancel;
                    }
                })
                ->requiresConfirmation()
                ->modalHeading(fn (): string => '現在の一覧に表示されているレコードをすべて最新の状態に更新しますか？（'.$this->listedReceivableCount().'件）')
                ->modalSubmitActionLabel('更新する')
                ->modalCancelActionLabel('キャンセル')
                ->action(function (): void {
                    $query = $this->getFilteredTableQuery();
                    $customers = $query instanceof Builder ? $query->toBase()->reorder()->distinct()->pluck('receivable_months.customer_id')->all() : [];
                    $count = $this->listedReceivableCount();

                    $ledger = app(ReceivableLedger::class);
                    foreach ($customers as $customerId) {
                        $ledger->refresh((int) $customerId);
                    }
                    Notification::make()->success()->title('売掛を更新しました（'.$count.'件・'.count($customers).'得意先）')->send();
                }),
            $this->createMonthAction('createCurrentMonth', '当月未作成一括登録', YearMonth::fromDate(CarbonImmutable::now())),
            $this->createMonthAction('createPreviousMonth', '前月未作成一括登録', YearMonth::fromDate(CarbonImmutable::now()->startOfMonth()->subMonth())),
        ];
    }

    private function listedReceivableCount(): int
    {
        $query = $this->getFilteredTableQuery();

        return $query instanceof Builder ? $query->toBase()->reorder()->count() : 0;
    }

    /** 当月 / 前月未作成一括登録: the missing records of the month, for the customers with a ledger (of the chosen 締日 on 23-2). */
    private function createMonthAction(string $name, string $label, YearMonth $month): Action
    {
        $cycle = $this->basis() === ReceivableBasis::Cycle;

        return Action::make($name)
            ->label($label)
            ->icon(str_starts_with($name, 'createCurrent') ? Heroicon::OutlinedCalendar : Heroicon::OutlinedBackward)
            ->visible(fn (): bool => $this->isAppAdministrator())
            ->modalHeading($month->label().' の売掛レコード未作成分を作成しますか？')
            ->modalSubmitActionLabel('作成する')
            ->modalCancelActionLabel('キャンセル')
            ->schema($cycle ? [
                Select::make('closing_day')->label('対象締日')->placeholder('-----')->native(false)->required()
                    ->options(collect(ClosingDay::cases())->reject(fn (ClosingDay $day): bool => $day === ClosingDay::AdHoc)
                        ->mapWithKeys(fn (ClosingDay $day): array => [$day->value => $day->value])->all()),
            ] : [])
            ->action(function (array $data) use ($month, $cycle): void {
                $basis = $this->basis();
                $customers = ReceivableMonth::query()->ofBasis($basis)->distinct()->pluck('customer_id');
                if ($cycle) {
                    $customers = Partner::query()->whereKey($customers->all())->where('closing_day', (string) ($data['closing_day'] ?? ''))->pluck('id');
                }
                $before = ReceivableMonth::query()->ofBasis($basis)->where('year_month', $month->key())->count();

                $ledger = app(ReceivableLedger::class);
                foreach ($customers as $customerId) {
                    $ledger->refresh((int) $customerId);
                }

                $created = ReceivableMonth::query()->ofBasis($basis)->where('year_month', $month->key())->count() - $before;
                Notification::make()->success()->title('処理が完了しました（'.$month->label().'：'.max(0, $created).'件作成）')->send();
            });
    }

    protected function basis(): ReceivableBasis
    {
        /** @var class-string<ReceivableMonthResource> $resource */
        $resource = static::getResource();

        return $resource::basis();
    }
}
