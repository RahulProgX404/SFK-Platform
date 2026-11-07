<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\PersonalOverview;
use Modules\Analytics\Filament\Dashboard\ChoosesYear;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\TrendChart;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Filament\Support\AppFormat;

/**
 * 勤務時間・収入の推移 (row 1 for users without 財務分析を表示, under 個人の概要; bug.md 2026-11-07, personal-growth.png): the
 * user's own 勤務時間 and 収入 of their 08. 日報入力 (PersonalOverview::monthly) per month of the chosen year — two charts
 * side by side, each on its own axis (hours and yen are never drawn on one). 収入 comes from 作業単価, so like 個人の
 * 概要 it is shown only to whoever may see the person's rates (EmployeeDataAccess::canViewRates).
 */
final class WorkTrendCard extends DashboardCard
{
    use ChoosesYear;

    public const COLORS = ['hours' => '#2a78d6', 'income' => '#159e6e'];

    protected static ?int $sort = 1;

    protected string $view = 'analytics::widgets.work-trend';

    public static function canView(): bool
    {
        $user = self::actor();

        return $user !== null && ! $user->seesFinancialAnalysis() && EmployeeDataAccess::canViewRates($user, $user);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $year = $this->chosenYear();
        $user = self::actor();
        $monthly = $user !== null
            ? app(PersonalOverview::class)->monthly($user, $year)
            : ['hours' => array_fill(1, 12, 0.0), 'income' => array_fill(1, 12, 0)];
        $hours = $this->upToToday($monthly['hours'], $year);
        $income = $this->upToToday($monthly['income'], $year);

        return [
            'shownYear' => $year, // not `year`: the public property of the choice would shadow it
            'years' => $this->yearChoices(),
            'totalHours' => (float) array_sum(array_filter($hours, fn (?float $value): bool => $value !== null)),
            'totalIncome' => (int) array_sum(array_filter($income, fn (?int $value): bool => $value !== null)),
            'hoursChart' => TrendChart::build(
                [['key' => 'hours', 'label' => '勤務時間', 'color' => self::COLORS['hours'], 'values' => $hours]],
                fn (int|float $value): string => TrendChart::trim((float) $value),
                fn (int|float $value): string => TrendChart::trim((float) $value).'時間',
            ),
            'incomeChart' => TrendChart::build(
                [['key' => 'income', 'label' => '収入', 'color' => self::COLORS['income'], 'values' => $income]],
                TrendChart::yenAxis(...),
                fn (int|float $value): string => AppFormat::yen((int) $value),
            ),
        ];
    }
}
