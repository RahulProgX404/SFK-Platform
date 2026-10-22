<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
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
 * - the legacy list buttons for administrators: 一括更新 — the months of the customers in the list recalculated (23 page 6, 23-2 page 8) —
 *   and 当月未作成一括登録 / 前月未作成一括登録 — the month's missing records created for every customer with a ledger
 *   (23 pages 4 / 5, 23-2 pages 6 / 7 for the customers of a chosen 締日). The ledger creates the months while a balance is
 *   open (BR-AR-05) whenever a sale or receipt is saved; these actions catch up the months nothing was saved in;
 * - 23-2 only: 「[新]一括更新」 of the legacy JS (scriptRefreshAction()).
 */
abstract class ListReceivableMonths extends AppListRecords
{
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
        return ReceivablesTable::filterFields($this->basis());
    }

    public function inlineFields(): array
    {
        return ReceivablesTable::inlineFields();
    }

    /** 合計 under the search row (render hook of the table toolbar, SalesServiceProvider); not for calendar views. */
    public static function totalsBar(): string
    {
        $page = Livewire::current();

        return $page instanceof self && ! $page->currentView()->isCalendar()
            ? view('sales::filament.receivable-totals', ['page' => $page])->render()
            : '';
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
                ->visible(fn (): bool => self::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('現在の一覧に表示されているレコードをすべて最新の状態に更新しますか？')
                ->modalSubmitActionLabel('更新する')
                ->modalCancelActionLabel('キャンセル')
                ->action(function (): void {
                    $query = $this->getFilteredTableQuery();
                    $customers = $query instanceof Builder ? $query->toBase()->reorder()->distinct()->pluck('receivable_months.customer_id')->all() : [];
                    if ($customers === []) {
                        Notification::make()->warning()->title('処理可能なレコードが存在しません。一覧の条件設定を確認してください。')->send();

                        return;
                    }

                    $ledger = app(ReceivableLedger::class);
                    foreach ($customers as $customerId) {
                        $ledger->refresh((int) $customerId);
                    }
                    Notification::make()->success()->title('売掛を更新しました（'.count($customers).'得意先）')->send();
                }),
            $this->createMonthAction('createCurrentMonth', '当月未作成一括登録', YearMonth::fromDate(CarbonImmutable::now())),
            $this->createMonthAction('createPreviousMonth', '前月未作成一括登録', YearMonth::fromDate(CarbonImmutable::now()->startOfMonth()->subMonth())),
            ...($this->basis() === ReceivableBasis::Cycle ? [$this->scriptRefreshAction()] : []),
        ];
    }

    /**
     * 23-2: 「[新]一括更新」 of 23-2_AccountsReceivableManagement.bundle.js (administrators): the records of the list brought
     * up to date (前月売掛残, 当月入金額, the sales per rate — the ledger of their customers recalculated), refused on the
     * view 売掛一覧 (too many records) and on an empty list.
     */
    private function scriptRefreshAction(): Action
    {
        return Action::make('refreshListedReceivables')
            ->label('[新]一括更新')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->visible(fn (): bool => self::isAdministrator())
            ->mountUsing(function (): void {
                if ($this->currentView()->name === '売掛一覧') {
                    Notification::make()->warning()->title('売掛一覧はレコード数が多いため一覧条件を変更の上実行してください。')->send();

                    throw new Cancel;
                }
                if ($this->listedReceivableCount() === 0) {
                    Notification::make()->warning()->title('処理可能なレコードが存在しません。一覧の条件設定を確認してください。')->send();

                    throw new Cancel;
                }
            })
            ->requiresConfirmation()
            ->modalHeading(fn (): string => '現在の一覧に表示されているレコードをすべて最新の状態に更新します。（'.$this->listedReceivableCount().'件）よろしいですか？')
            ->modalDescription('*レコード未作成月があると正確な更新ができない可能性があります。*未作成のレコードがある場合は先に作成してください。')
            ->modalSubmitActionLabel('OK')
            ->modalCancelActionLabel('キャンセル')
            ->action(function (): void {
                $query = $this->getFilteredTableQuery();
                $customers = $query instanceof Builder ? $query->toBase()->reorder()->distinct()->pluck('receivable_months.customer_id')->all() : [];
                $count = $this->listedReceivableCount();

                $ledger = app(ReceivableLedger::class);
                foreach ($customers as $customerId) {
                    $ledger->refresh((int) $customerId);
                }
                Notification::make()->success()->title('更新が完了しました。（'.$count.'件）')->send();
            });
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
            ->visible(fn (): bool => self::isAdministrator())
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

    private static function isAdministrator(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator();
    }
}
