<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use DateTimeImmutable;
use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Domain\SalesCoverage;
use Modules\Analytics\Filament\Dashboard\ChoosesYear;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\TrendChart;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\Tip;

/**
 * 売上の推移 (row 2; bug.md 2026-11-07 / 2026-11-09 / 2026-11-11, personal-growth.png): the sales per month of the chosen
 * calendar year against the year before, with the year's total and its change against the same days of the year before.
 * 個人 (個人のみ, and 全体 switched to 個人): the user's own sales (the first 担当者, SalesDashboard::forStaff), about 70% of
 * the row with 最近表示した項目 next to it; 全体: everyone's, half the row before 上位の売上先 and 売れ筋 TOP3.
 *
 * Only what the sales data covers is drawn (SalesCoverage, 2026-11-14): a month before the first or after the last
 * recorded 売上日 has no data — no point, no line across it, 「データなし」 — rather than ¥0, and the month the data ends
 * in (this month so far, or the last month of an import) is marked incomplete. 前年同期比 compares the same days of both
 * years that have data (e.g. 5/1〜10/16 of each), never a part of a month with a whole one or data with none.
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

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $year = $this->chosenYear();
        $service = $this->salesData();
        $coverage = $this->coverage();
        $current = $this->shaped($service->monthly($year), $year, $coverage);
        $previous = $this->shaped($service->monthly($year - 1), $year - 1, $coverage);
        $comparison = $this->sameDaysYearBefore($service, $coverage, $year);
        $total = (int) array_sum(array_filter($current['values'], fn (?int $amount): bool => $amount !== null));
        $totalNote = $this->daysWithData($coverage, $year);

        return [
            'span' => $this->inPersonalLayout() ? 8 : 6,
            'shownYear' => $year, // not `year`: the public property of the choice would shadow it
            'years' => $this->yearChoices(),
            'total' => $total,
            'totalNote' => $totalNote,
            'change' => $comparison['change'],
            'comparison' => $comparison,
            'chart' => TrendChart::build([
                ['key' => 'current', 'label' => "{$year}年", 'color' => self::COLORS['current'], ...$current],
                // the year before is the comparison: dashed, without the area
                ['key' => 'previous', 'label' => ($year - 1).'年', 'color' => self::COLORS['previous'], 'dashed' => true, ...$previous],
            ], TrendChart::yenAxis(...), fn (int|float $amount): string => AppFormat::yen((int) $amount), fn (int|float $difference): string => Tip::signedYen((int) $difference)),
        ];
    }

    /**
     * The months of a year as the chart draws them: the months after this one have no value yet, months without sales
     * data none either (and say so), the month the data starts or ends in carries its note.
     *
     * @param  array<int, int>  $amounts  month => 売上金額
     * @return array{values: array<int, int|null>, notes: array<int, string>, missing: list<int>}
     */
    private function shaped(array $amounts, int $year, SalesCoverage $coverage): array
    {
        $values = $this->upToToday($amounts, $year);
        $notes = [];
        $missing = [];
        foreach ($values as $month => $amount) {
            if ($amount === null) {
                continue;
            }
            [$state, $note] = $coverage->month($year, $month);
            if ($state === 'none') {
                $values[$month] = null;
                $missing[] = $month;
            } elseif ($note !== null) {
                $notes[$month] = $note;
            }
        }

        return ['values' => $values, 'notes' => $notes, 'missing' => $missing];
    }

    /**
     * 前年同期比 over the days both years have sales data: from 1 January (or a year after the first 売上日) to the last
     * day with data this year (today, the last 売上日 of a stopped import, or 31 December).
     *
     * @return array{change: float|null, days: string|null, now: int|null, label: string|null, before: int|null}
     */
    private function sameDaysYearBefore(SalesDashboard $service, SalesCoverage $coverage, int $year): array
    {
        $none = ['change' => null, 'days' => null, 'now' => null, 'label' => null, 'before' => null];
        $start = $coverage->first;
        $end = $coverage->end();
        if ($start === null || $end === null) {
            return $none;
        }

        $from = max(new DateTimeImmutable("{$year}-01-01"), $start->modify('+1 year'));
        $to = min(new DateTimeImmutable("{$year}-12-31"), $end, $this->today());
        if ($from > $to) {
            return $none;
        }

        $compared = Period::between($from->format('Y-m-d'), $to->format('Y-m-d'), $this->today());
        $now = $service->totals($compared);
        $yearBefore = Period::between($from->modify('-1 year')->format('Y-m-d'), $to->modify('-1 year')->format('Y-m-d'), $this->today());
        $before = $service->totals($yearBefore);

        $change = self::change($now['amount'], $before['amount']);

        return [
            'change' => $change,
            'days' => $from->format('n/j').'〜'.$to->format('n/j'),
            'now' => $now['amount'],
            'label' => $yearBefore->shortLabel(),
            'before' => $before['amount'],
        ];
    }

    /** The days of the year the total covers (「1/1〜10/16」), null for the whole year. */
    private function daysWithData(SalesCoverage $coverage, int $year): ?string
    {
        $start = $coverage->first;
        $end = $coverage->end();
        if ($start === null || $end === null) {
            return 'データなし';
        }

        $first = new DateTimeImmutable("{$year}-01-01");
        $last = new DateTimeImmutable("{$year}-12-31");
        $from = max($first, $start);
        $to = min($last, $end, $this->today());
        if ($from > $to) {
            return 'データなし';
        }

        return $from == $first && $to == $last ? null : $from->format('n/j').'〜'.$to->format('n/j');
    }
}
