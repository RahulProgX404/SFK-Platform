<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Analytics\Models\SalesForecast;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Models\Payment;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\Sale;

/**
 * The money of the dashboard (rows 1 and 2, 財務分析を表示): the sales of a period from 06. 売上入力 (売上日, 売上金額 税抜),
 * where they stand — 未請求 (no 請求書 yet), 入金待ち (invoiced, not yet paid), 期限超過 (invoiced, not paid by the
 * customer's 回収予定: 請求日 + 回収予定区分 months, on the 回収予定日), 入金済 (the invoice's 入金 cover its 請求金額) —,
 * the 売上目標 of 25. 販売先別売上計画 (the monthly plans of the months the period touches), the cash of 21. 売入金管理
 * (入金日) and 07. 支払入力 (支払日), and the top 売上先 / 担当者 / 品名. Everything is read from what the user may see
 * (the apps' onlyVisible()).
 *
 * 個人 (forStaff(), bug.md 2026-11-06 — 財務分析の範囲 個人のみ, or 全体 switched to 個人): only the sales whose 担当者 is
 * the user (the first 担当者, as 売上分析 counts them), the user's own 予想 (売上分析) as the target and the 入金 of
 * 21. 売入金管理 they are the 担当者 of; 支払 belong to nobody, so 入出金 shows what is still to come in instead.
 */
final class SalesDashboard
{
    public const STATUSES = ['paid' => '入金済', 'pending' => '入金待ち', 'overdue' => '期限超過', 'unbilled' => '未請求'];

    /** the 担当者 whose sales are shown (個人), null = everyone's */
    private ?int $staffId = null;

    /** The same figures for one 担当者 only (null: everyone's). */
    public function forStaff(?int $userId): self
    {
        $copy = clone $this;
        $copy->staffId = $userId;

        return $copy;
    }

    public function isPersonal(): bool
    {
        return $this->staffId !== null;
    }

    /**
     * @return array{amount: int, count: int, statuses: array<string, array{count: int, amount: int}>, parts: list<int>}
     *                                                                                                                   statuses by STATUSES key; parts: the number of sales in each part of the period
     */
    public function overview(Period $period, DateTimeImmutable $today, int $parts = 7): array
    {
        $settled = 'select coalesce(sum(receipts.settled_amount), 0) from receipts where receipts.invoice_id = sales.invoice_id';
        $billed = 'select coalesce(sum(receipts.billed_amount), 0) from receipts where receipts.invoice_id = sales.invoice_id';
        $rows = $this->sales($period)
            ->leftJoin('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->leftJoin('partners', 'partners.id', '=', 'sales.customer_id')
            ->selectRaw("sales.amount, sales.sold_on, sales.invoice_id, invoices.invoiced_on, partners.collection_month, partners.collection_day, ({$settled}) as settled, ({$billed}) as billed")
            ->toBase()
            ->get();

        $statuses = array_fill_keys(array_keys(self::STATUSES), ['count' => 0, 'amount' => 0]);
        $bounds = $period->parts($parts);
        $counts = array_fill(0, count($bounds), 0);
        foreach ($rows as $row) {
            $status = $this->status($row, $today);
            $statuses[$status]['count']++;
            $statuses[$status]['amount'] += (int) $row->amount;

            $day = substr((string) $row->sold_on, 0, 10);
            foreach ($bounds as $index => [$from, $after]) {
                if ($day >= $from && $day < $after) {
                    $counts[$index]++;
                    break;
                }
            }
        }

        return [
            'amount' => (int) $rows->sum('amount'),
            'count' => $rows->count(),
            'statuses' => $statuses,
            'parts' => $counts,
        ];
    }

    /** @return array{amount: int, count: int} */
    public function totals(Period $period): array
    {
        $row = $this->sales($period)->selectRaw('coalesce(sum(sales.amount), 0) as amount, count(*) as count')->toBase()->first();

        return ['amount' => (int) ($row->amount ?? 0), 'count' => (int) ($row->count ?? 0)];
    }

    /**
     * 売上目標: the monthly plans of 25. 販売先別売上計画 for the months the period touches; null without plans. 個人: the
     * 担当者's 予想 of 売上分析 for those months.
     */
    public function target(Period $period): ?int
    {
        if ($this->staffId !== null) {
            return $this->forecast($period, $this->staffId);
        }

        $total = null;
        foreach ($period->months() as [$year, $month]) {
            [$from, $after] = FiscalYear::days(FiscalYear::ofMonth($year, $month));
            $sum = SalesPlanResource::onlyVisible(SalesPlan::query())
                ->where('planned_on', '>=', $from)->where('planned_on', '<', $after)
                ->sum('plan_'.FiscalYear::column($month));
            if ((int) $sum !== 0) {
                $total = ($total ?? 0) + (int) $sum;
            }
        }

        return $total;
    }

    /**
     * 入金 (21. 売入金管理, 入金日) and 出金 (07. 支払入力, 支払日). 個人: the 入金 of the receipts the 担当者 has; no 出金
     * (a 支払 has no 担当者).
     *
     * @return array{inflow: int, outflow: int, net: int}
     */
    public function cashFlow(Period $period): array
    {
        $inflow = (int) ReceiptResource::onlyVisible(Receipt::query())
            ->join('receipt_lines', 'receipt_lines.receipt_id', '=', 'receipts.id')
            ->where('receipt_lines.received_on', '>=', $period->start())->where('receipt_lines.received_on', '<', $period->after())
            ->when($this->staffId !== null, fn (Builder $query) => $query->whereExists(fn ($exists) => $exists
                ->from('receipt_user')->whereColumn('receipt_user.receipt_id', 'receipts.id')->where('receipt_user.user_id', $this->staffId)))
            ->sum('receipt_lines.amount');
        if ($this->staffId !== null) {
            return ['inflow' => $inflow, 'outflow' => 0, 'net' => $inflow];
        }

        $outflow = (int) PaymentResource::onlyVisible(Payment::query())
            ->where('payments.paid_on', '>=', $period->start())->where('payments.paid_on', '<', $period->after())
            ->sum('payments.total');

        return ['inflow' => $inflow, 'outflow' => $outflow, 'net' => $inflow - $outflow];
    }

    /** @return list<array{id: int, name: string, code: string|null, count: int, amount: int}> by 売上金額 */
    public function topCustomers(Period $period, int $limit = 5): array
    {
        return $this->sales($period)
            ->join('partners', 'partners.id', '=', 'sales.customer_id')
            ->groupBy('partners.id', 'partners.name', 'partners.code')
            ->selectRaw('partners.id, partners.name, partners.code, count(*) as count, sum(sales.amount) as amount')
            ->orderByDesc('amount')->orderBy('partners.name')
            ->limit($limit)
            ->toBase()->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'code' => $row->code, 'count' => (int) $row->count, 'amount' => (int) $row->amount])
            ->all();
    }

    /** @return list<array{id: int, name: string, count: int, amount: int}> 担当者 (the first of a sale, as 売上分析) by 売上金額 */
    public function topStaff(Period $period, int $limit = 5): array
    {
        $rows = $this->sales($period)
            ->join('sale_user as first_staff', function (JoinClause $join): void {
                $join->on('first_staff.sale_id', '=', 'sales.id')
                    ->whereRaw('first_staff.id = (select min(staff.id) from sale_user as staff where staff.sale_id = sales.id)');
            })
            ->groupBy('first_staff.user_id')
            ->selectRaw('first_staff.user_id, count(*) as count, sum(sales.amount) as amount')
            ->orderByDesc('amount')
            ->limit($limit)
            ->toBase()->get();
        $names = User::query()->whereIn('id', $rows->pluck('user_id')->all())->pluck('name', 'id')->all();

        return $rows->map(fn (object $row): array => ['id' => (int) $row->user_id, 'name' => (string) ($names[(int) $row->user_id] ?? ''), 'count' => (int) $row->count, 'amount' => (int) $row->amount])->all();
    }

    /**
     * The newest sales of the period (個人: the 担当者's own), for 担当者別売上 in 個人.
     *
     * @return list<array{id: int, sold_on: string, customer: string, product: string|null, amount: int, url: string|null}>
     */
    public function latestSales(Period $period, int $limit = 5): array
    {
        return $this->sales($period)
            ->with('customer:id,name')
            ->orderByDesc('sales.sold_on')->orderByDesc('sales.id')
            ->limit($limit)
            ->select('sales.*')
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'sold_on' => $sale->sold_on->format('Y/m/d'),
                'customer' => (string) ($sale->customer->name ?? ''),
                'product' => $sale->product_name,
                'amount' => (int) $sale->amount,
                'url' => SaleResource::canView($sale) ? SaleResource::getUrl('view', ['record' => $sale]) : null,
            ])
            ->all();
    }

    /** @return list<array{name: string, count: int, amount: int}> 品名 by 売上金額 */
    public function topProducts(Period $period, int $limit = 3): array
    {
        return $this->sales($period)
            ->whereNotNull('sales.product_name')->where('sales.product_name', '!=', '')
            ->groupBy('sales.product_name')
            ->selectRaw('sales.product_name as name, count(*) as count, sum(sales.amount) as amount')
            ->orderByDesc('amount')->orderBy('sales.product_name')
            ->limit($limit)
            ->toBase()->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'count' => (int) $row->count, 'amount' => (int) $row->amount])
            ->all();
    }

    /** The day a customer pays an invoice: 請求日 + 回収予定区分 months, on the 回収予定日 (「末日」, 「25日」 …; else the last day). */
    public static function dueOn(DateTimeImmutable $invoicedOn, ?string $collectionMonth, ?string $collectionDay): DateTimeImmutable
    {
        $months = CollectionMonth::tryFrom((string) $collectionMonth)?->monthsLater() ?? 1;
        $month = $invoicedOn->modify('first day of this month')->modify('+'.$months.' months');
        $last = (int) $month->format('t');
        $day = preg_match('/(\d{1,2})/', (string) $collectionDay, $match) === 1 ? min((int) $match[1], $last) : $last;

        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), max(1, $day));
    }

    private function status(object $row, DateTimeImmutable $today): string
    {
        if ($row->invoice_id === null) {
            return 'unbilled';
        }
        if ((int) $row->billed > 0 && (int) $row->settled >= (int) $row->billed) {
            return 'paid';
        }
        if ($row->invoiced_on !== null) {
            $invoiced = new DateTimeImmutable(substr((string) $row->invoiced_on, 0, 10));
            if (self::dueOn($invoiced, $row->collection_month, $row->collection_day) < $today->setTime(0, 0)) {
                return 'overdue';
            }
        }

        return 'pending';
    }

    /** 予想 of 売上分析 (sales_forecasts) of one 担当者 for the months the period touches; null without any. */
    private function forecast(Period $period, int $userId): ?int
    {
        $total = null;
        $years = [];
        foreach ($period->months() as [$year, $month]) {
            $fiscalYear = FiscalYear::ofMonth($year, $month);
            $years[$fiscalYear] ??= SalesForecast::query()->where('user_id', $userId)->where('fiscal_year', $fiscalYear)->first()?->months('forecast') ?? [];
            $amount = (int) ($years[$fiscalYear][$month] ?? 0);
            if ($amount !== 0) {
                $total = ($total ?? 0) + $amount;
            }
        }

        return $total;
    }

    /** @return Builder<Sale> the visible sales of the period (個人: those whose first 担当者 is the user) */
    private function sales(Period $period): Builder
    {
        return SaleResource::onlyVisible(Sale::query())
            ->where('sales.sold_on', '>=', $period->start())
            ->where('sales.sold_on', '<', $period->after())
            ->when($this->staffId !== null, fn (Builder $query) => $query->whereExists(fn ($exists) => $exists
                ->from('sale_user as mine')
                ->whereColumn('mine.sale_id', 'sales.id')
                ->where('mine.user_id', $this->staffId)
                ->whereRaw('mine.id = (select min(staff.id) from sale_user as staff where staff.sale_id = sales.id)')));
    }
}
