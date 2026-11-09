<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\ChoosesYear;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\TrendChart;
use Modules\Core\Filament\Support\AppFormat;

/**
 * 売上の推移 (row 2 of the 個人 layout — 個人のみ, and 全体 switched to 個人 —, bug.md 2026-11-07 / 2026-11-09,
 * personal-growth.png): the user's own sales (the sales they are the first 担当者 of, SalesDashboard::forStaff) per month of
 * the chosen year against the year before, with the year's total and its change against the same months of the year
 * before; about 70% of the row, 最近表示した項目 next to it. 全体 gets 上位の売上先, 担当者別売上 and 売れ筋 TOP3 in this row
 * instead.
 */
final class SalesTrendCard extends DashboardCard
{
    use ChoosesYear;

    public const COLORS = ['current' => '#2a78d6', 'previous' => '#159e6e'];

    protected static ?int $sort = 4;

    protected string $view = 'analytics::widgets.sales-trend';

    public static function canView(): bool
    {
        return self::showsMoney();
    }

    /** Only in the 個人 layout. */
    protected static function shownInLayout(bool $personal): bool
    {
        return $personal;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $year = $this->chosenYear();
        $service = $this->salesData();
        $current = $this->upToToday($service->monthly($year), $year);
        $previous = $service->monthly($year - 1);

        $total = (int) array_sum(array_filter($current, fn (?int $amount): bool => $amount !== null));
        $sameMonths = (int) array_sum(array_intersect_key($previous, array_filter($current, fn (?int $amount): bool => $amount !== null)));

        return [
            'shownYear' => $year, // not `year`: the public property of the choice would shadow it
            'years' => $this->yearChoices(),
            'total' => $total,
            'change' => self::change($total, $sameMonths),
            'chart' => TrendChart::build([
                ['key' => 'current', 'label' => "{$year}年", 'color' => self::COLORS['current'], 'values' => $current],
                ['key' => 'previous', 'label' => ($year - 1).'年', 'color' => self::COLORS['previous'], 'values' => $previous],
            ], TrendChart::yenAxis(...), fn (int|float $amount): string => AppFormat::yen((int) $amount)),
        ];
    }
}
