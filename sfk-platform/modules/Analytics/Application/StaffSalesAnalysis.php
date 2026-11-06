<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\SalesForecast;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\Sale;

/**
 * 分析 → 売上分析 (legacy SalesAnalysis.bundle.js, 担当者別 売上ダッシュボード): the 売上金額 (税抜) of 06. 売上入力 per 担当者,
 * business year (May–April) and month (売上日), and the 売上先 behind one month. A sale counts for its first 担当者, as
 * in the legacy script. The sums are made from the sales the user may see (SaleResource::onlyVisible), every time —
 * the legacy copy into an app once a day is not kept. 個人 (forStaff(); 財務分析の範囲 個人のみ, bug.md 2026-11-06): only
 * the sales, 予想 and 仕掛 of that 担当者.
 */
final class StaffSalesAnalysis
{
    /** the only 担当者 shown (個人), null = everyone */
    private ?int $staffId = null;

    /** The same figures for one 担当者 only (null: everyone's). */
    public function forStaff(?int $userId): self
    {
        $copy = clone $this;
        $copy->staffId = $userId;

        return $copy;
    }

    /** @return list<int> the business years with sales, oldest first, up to the current one */
    public function years(): array
    {
        $first = $this->sales()->min('sales.sold_on');
        $current = FiscalYear::startingYear(now());
        $from = $first !== null ? FiscalYear::startingYear(new \DateTimeImmutable((string) $first)) : $current;

        return range(min($from, $current), $current);
    }

    /**
     * @param  list<int>  $years
     * @return array<int, array<int, array<int, int>>> 担当者 (user id) => business year => calendar month => 売上金額
     */
    public function actuals(array $years): array
    {
        if ($years === []) {
            return [];
        }

        $month = MonthBucket::sql('sales.sold_on');
        $rows = $this->sales()
            ->where('sales.sold_on', '>=', FiscalYear::days(min($years))[0])
            ->where('sales.sold_on', '<', FiscalYear::days(max($years))[1])
            ->groupBy('first_staff.user_id', DB::raw($month))
            ->selectRaw("first_staff.user_id as staff_id, {$month} as month, sum(sales.amount) as total")
            ->toBase()
            ->get();

        $actuals = [];
        foreach ($rows as $row) {
            [$year, $calendarMonth] = MonthBucket::parse((string) $row->month);
            $fiscalYear = FiscalYear::ofMonth($year, $calendarMonth);
            if (in_array($fiscalYear, $years, true)) {
                $actuals[(int) $row->staff_id][$fiscalYear][$calendarMonth] = (int) $row->total;
            }
        }

        return $actuals;
    }

    /**
     * The 担当者 with sales in the years or 予想 / 仕掛, by 担当者コード then name.
     *
     * @param  list<int>  $years
     * @param  array<int, mixed>  $actuals  from actuals()
     * @return array<int, string> user id => name
     */
    public function staff(array $years, array $actuals): array
    {
        $ids = array_keys($actuals);
        if ($years !== []) {
            $ids = [...$ids, ...$this->forecastQuery()->whereIn('fiscal_year', $years)->pluck('user_id')->all()];
        }

        return User::query()->whereIn('id', array_unique($ids))
            ->orderByRaw('sales_staff_code is null')->orderBy('sales_staff_code')->orderBy('name')
            ->pluck('name', 'id')->all();
    }

    /** @return array<int, SalesForecast> user id => 予想 / 仕掛 of the business year */
    public function forecasts(int $fiscalYear): array
    {
        return $this->forecastQuery()->where('fiscal_year', $fiscalYear)->get()->keyBy('user_id')->all();
    }

    /** Stores one month of 予想 (forecast) or 仕掛 (wip). */
    public function saveForecast(int $userId, int $fiscalYear, string $kind, int $month, int $amount): void
    {
        if (! array_key_exists($kind, SalesForecast::KINDS) || ! in_array($month, FiscalYear::MONTHS, true)) {
            throw new \InvalidArgumentException('Unknown forecast cell.');
        }

        $forecast = SalesForecast::query()->firstOrNew(['user_id' => $userId, 'fiscal_year' => $fiscalYear]);
        $forecast->setAttribute($kind.'_'.FiscalYear::column($month), $amount);
        $forecast->save();
    }

    /**
     * 売上先別 明細 of one month (all 担当者 when none is given): the customers by 得意先コード, each with its sales lines.
     *
     * @return list<array{code: string|null, name: string, amount: int, lines: list<array{date: string, product: string|null, amount: int}>}>
     */
    public function customers(int $fiscalYear, int $month, ?int $staffId = null): array
    {
        $year = FiscalYear::calendarYear($fiscalYear, $month);
        $from = sprintf('%04d-%02d-01', $year, $month);
        $after = date('Y-m-d', (int) strtotime($from.' +1 month'));

        $sales = $this->sales()
            ->with('customer:id,code,name')
            ->where('sales.sold_on', '>=', $from)->where('sales.sold_on', '<', $after)
            ->when($staffId !== null, fn (Builder $query) => $query->where('first_staff.user_id', $staffId))
            ->orderBy('sales.sold_on')->orderBy('sales.id')
            ->select('sales.*')
            ->get();

        $customers = [];
        foreach ($sales as $sale) {
            $key = (string) $sale->customer_id;
            $customers[$key] ??= ['code' => $sale->customer->code, 'name' => $sale->customer->name, 'amount' => 0, 'lines' => []];
            $customers[$key]['amount'] += $sale->amount;
            $customers[$key]['lines'][] = ['date' => $sale->sold_on->format('Y/m/d'), 'product' => $sale->product_name, 'amount' => $sale->amount];
        }

        $list = array_values($customers);
        usort($list, fn (array $a, array $b): int => [$a['code'] === null, $a['code'], $a['name']] <=> [$b['code'] === null, $b['code'], $b['name']]);

        return $list;
    }

    /**
     * The visible sales with their first 担当者 (`first_staff`), the one the legacy script counted.
     *
     * @return Builder<Sale>
     */
    private function sales(): Builder
    {
        return SaleResource::onlyVisible(Sale::query())
            ->join('sale_user as first_staff', function (JoinClause $join): void {
                $join->on('first_staff.sale_id', '=', 'sales.id')
                    ->whereRaw('first_staff.id = (select min(staff.id) from sale_user as staff where staff.sale_id = sales.id)');
            })
            ->when($this->staffId !== null, fn (Builder $query) => $query->where('first_staff.user_id', $this->staffId));
    }

    /** @return Builder<SalesForecast> 予想 / 仕掛 (個人: the 担当者's own) */
    private function forecastQuery(): Builder
    {
        return SalesForecast::query()->when($this->staffId !== null, fn (Builder $query) => $query->where('user_id', $this->staffId));
    }
}
