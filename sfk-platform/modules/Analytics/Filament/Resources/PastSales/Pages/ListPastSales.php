<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\PastSales\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Analytics\Filament\Resources\PastSales\PastSaleResource;
use Modules\Analytics\Filament\Resources\PastSales\Tables\PastSalesTable;
use Modules\Analytics\Models\PastSale;
use Modules\Core\Filament\AppPages\AppListRecords;

/**
 * List of 22. 過去の売上データ. The legacy graphs are カスタマイズ形式 views over the records of the view (and 絞り込む):
 * 年間売上 — 年間合計 by 担当者 and 決算年度, one bar per year, largest 担当者 first (legacy: sum by 担当者 × 決算年度, column
 * chart) — and 担当者別売上比較 — the largest and the smallest 年間合計 of each 担当者 (legacy: max / min by 担当者).
 */
final class ListPastSales extends AppListRecords
{
    protected static string $resource = PastSaleResource::class;

    public function listColumns(): array
    {
        return PastSalesTable::columns();
    }

    /** The legacy views and graphs (SYSTEM_BRAIN APP_22 views.md). */
    public function defaultListViews(): array
    {
        return PastSalesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return PastSalesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return PastSalesTable::inlineFields();
    }

    public function customListViews(): array
    {
        return ['annual' => 'analytics::filament.past-sales-chart', 'range' => 'analytics::filament.past-sales-chart'];
    }

    /**
     * The data of the current graph: rows of 担当者 with their bars, the largest value (scale).
     *
     * @return array{kind: string, max: int, rows: list<array{label: string, bars: list<array{label: string, value: int, low?: int}>}>}
     */
    public function pastSalesChart(): array
    {
        $kind = (string) $this->currentView()->customView;
        $query = $this->getFilteredTableQuery();
        $sales = $query instanceof Builder ? $query->clone()->reorder()->get()->whereInstanceOf(PastSale::class) : collect();
        $people = $sales->groupBy(fn (PastSale $sale): string => (string) ($sale->salesperson ?? ''));

        if ($kind === 'range') {
            $rows = $people->map(fn (Collection $own, string $person): array => [
                'label' => $person !== '' ? $person : '（担当者なし）',
                'bars' => [['label' => '最大 / 最小', 'value' => (int) $own->max('total'), 'low' => (int) $own->min('total')]],
            ])->sortBy(fn (array $row): string => $row['label'])->values()->all();
        } else {
            $rows = $people->map(fn (Collection $own, string $person): array => [
                'label' => $person !== '' ? $person : '（担当者なし）',
                'sum' => (int) $own->sum('total'),
                'bars' => $own->groupBy(fn (PastSale $sale): string => (string) ($sale->fiscalYear() ?? '—'))
                    ->map(fn (Collection $year, string $label): array => ['label' => $label, 'value' => (int) $year->sum('total')])
                    ->sortKeys()->values()->all(),
            ])->sortByDesc('sum')->map(fn (array $row): array => ['label' => $row['label'], 'bars' => $row['bars']])->values()->all();
        }

        $max = 0;
        foreach ($rows as $row) {
            foreach ($row['bars'] as $bar) {
                $max = max($max, $bar['value']);
            }
        }

        return ['kind' => $kind, 'max' => $max, 'rows' => $rows];
    }
}
