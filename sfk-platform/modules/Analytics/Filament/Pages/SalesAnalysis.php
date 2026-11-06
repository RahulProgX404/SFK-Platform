<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Analytics\Application\StaffSalesAnalysis;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Filament\Pages\Concerns\HasAnalysisChrome;
use Modules\Analytics\Models\SalesForecast;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\Sales\Filament\Resources\Sales\SaleResource;

/**
 * 分析 → 売上分析 (/erp/analysis/sales; legacy SalesAnalysis.bundle.js, 担当者別 売上ダッシュボード), not an app yet: the
 * 売上金額 of 06. 売上入力 per 担当者, business year (May–April) and month, with 予想 and 仕掛 typed in for the current year
 * (administrators, or the 担当者 for their own rows); 月次 = a table per 担当者 and the totals, 年次 = the totals only; a
 * month opens its 売上先 and their sales lines. For users with 財務分析を表示 (User::seesFinancialAnalysis()) who may
 * view 06. 売上入力; the dashboard's 担当者別売上 leads here. 財務分析の範囲 個人のみ: only the user's own row, totals and
 * 売上先.
 */
final class SalesAnalysis extends Page
{
    use HasAnalysisChrome;

    protected static ?string $title = '売上分析';

    protected static ?string $slug = 'analysis/sales';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'analytics::filament.analysis-sales';

    /** @var list<int|string> 年度 shown */
    public array $years = [];

    /** monthly (月次) | yearly (年次) */
    public string $mode = 'monthly';

    /** @var list<int|string> 担当者 shown in 月次 */
    public array $staff = [];

    /** @var array{staff: int|null, year: int, month: int}|null the month whose 売上先 are open */
    public ?array $detail = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->seesFinancialAnalysis() && SaleResource::canViewAny();
    }

    public function mount(): void
    {
        $sales = $this->analysis();
        $current = FiscalYear::startingYear(now());
        $this->years = array_slice($sales->years(), -2);
        $this->staff = $this->defaultStaff($sales->actuals([$current, $current - 1]), $current);
    }

    public function showDetail(?int $staffId, int $year, int $month): void
    {
        if (in_array($month, FiscalYear::MONTHS, true)) {
            $this->detail = ['staff' => $staffId, 'year' => $year, 'month' => $month];
        }
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }

    /** 予想 / 仕掛 of the current business year: administrators, or the 担当者 on their own row. */
    public function saveForecast(int $userId, string $kind, int $month, mixed $value): void
    {
        abort_unless($this->canEditForecast($userId), 403);
        $sales = $this->analysis();

        $amount = is_numeric($value) ? (int) round((float) $value) : 0;
        if (abs($amount) > 999_999_999_999) {
            Notification::make()->title('金額が大きすぎます。')->danger()->send();

            return;
        }

        $sales->saveForecast($userId, FiscalYear::startingYear(now()), $kind, $month, $amount);
    }

    public function canEditForecast(int $userId): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->id === $userId || ($user->user_type->isAdministrator() && $user->seesOverallFinance());
    }

    /**
     * The sales the page sums: everyone's, or — 財務分析の範囲 個人のみ (bug.md 2026-11-06) — only the user's own as 担当者.
     */
    private function analysis(): StaffSalesAnalysis
    {
        $user = auth()->user();

        return app(StaffSalesAnalysis::class)->forStaff($user instanceof User && ! $user->seesOverallFinance() ? $user->id : null);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $current = FiscalYear::startingYear(now());
        $currentMonth = (int) now()->format('n');

        return [
            'months' => FiscalYear::MONTHS,
            'currentYear' => $current,
            // months of the current business year still to come are shown as 「－」
            'futureFrom' => array_search($currentMonth, FiscalYear::MONTHS, true) + 1,
            'sales' => $this->salesData($current, $currentMonth),
        ];
    }

    /** @return array<string, mixed> */
    private function salesData(int $current, int $currentMonth): array
    {
        $service = $this->analysis();
        $available = $service->years();
        $years = array_values(array_intersect($available, array_map('intval', $this->years)));
        sort($years);

        $actuals = $service->actuals(array_values(array_unique([...$years, $current, $current - 1])));
        $staff = $service->staff($years, $actuals);
        $forecasts = $service->forecasts($current);
        $kinds = fn (?SalesForecast $forecast): array => [
            'forecast' => $forecast?->months('forecast') ?? array_fill_keys(FiscalYear::MONTHS, 0),
            'wip' => $forecast?->months('wip') ?? array_fill_keys(FiscalYear::MONTHS, 0),
        ];

        // 年度別 合計 of every 担当者, and 予想 / 仕掛 of the current year
        $totals = [];
        foreach ([...$years, $current - 1, $current] as $year) {
            $totals[$year] = array_fill_keys(FiscalYear::MONTHS, 0);
            foreach ($actuals as $byYear) {
                foreach ($byYear[$year] ?? [] as $month => $amount) {
                    $totals[$year][$month] += $amount;
                }
            }
        }
        $planned = ['forecast' => array_fill_keys(FiscalYear::MONTHS, 0), 'wip' => array_fill_keys(FiscalYear::MONTHS, 0)];
        foreach ($forecasts as $forecast) {
            foreach ($kinds($forecast) as $kind => $months) {
                foreach ($months as $month => $amount) {
                    $planned[$kind][$month] += $amount;
                }
            }
        }

        // the year so far against the same months of the year before
        $elapsed = array_slice(FiscalYear::MONTHS, 0, (int) array_search($currentMonth, FiscalYear::MONTHS, true) + 1);
        $toDate = array_sum(array_intersect_key($totals[$current], array_flip($elapsed)));
        $lastToDate = array_sum(array_intersect_key($totals[$current - 1], array_flip($elapsed)));
        $ratio = $lastToDate > 0 ? round($toDate / $lastToDate * 100, 1) : null;

        $chosen = array_map('intval', $this->staff);
        $people = [];
        if ($this->mode === 'monthly') {
            foreach ($staff as $id => $name) {
                if (in_array($id, $chosen, true)) {
                    $people[] = [
                        'id' => $id,
                        'name' => $name,
                        'rows' => array_map(fn (int $year): array => ['year' => $year, 'months' => $this->months($actuals[$id][$year] ?? [])], $years),
                        'planned' => $kinds($forecasts[$id] ?? null),
                        'editable' => $this->canEditForecast($id),
                    ];
                }
            }
        }

        $detail = null;
        if ($this->detail !== null) {
            $customers = $service->customers($this->detail['year'], $this->detail['month'], $this->detail['staff']);
            $detail = [
                ...$this->detail,
                'staffName' => $this->detail['staff'] !== null ? ($staff[$this->detail['staff']] ?? '') : '全担当者',
                'calendarYear' => FiscalYear::calendarYear($this->detail['year'], $this->detail['month']),
                'customers' => $customers,
                'total' => array_sum(array_column($customers, 'amount')),
            ];
        }

        return [
            'available' => $available,
            'years' => $years,
            'staff' => $staff,
            'chosen' => $chosen,
            'people' => $people,
            'totals' => array_intersect_key($totals, array_flip($years)),
            'planned' => $planned,
            'detail' => $detail,
            'cards' => [
                SummaryCard::make('今年度の売上', $toDate),
                SummaryCard::make('前年同期の売上', $lastToDate, tone: 'slate', icon: 'heroicon-o-calendar'),
                [...SummaryCard::make('前年同期比', $ratio, 'percent'), 'tone' => $ratio !== null && $ratio < 100 ? 'red' : 'green'],
                SummaryCard::make('今年度の予想', array_sum($planned['forecast']), tone: 'purple', icon: 'heroicon-o-presentation-chart-line'),
                SummaryCard::make('仕掛', array_sum($planned['wip']), tone: 'amber', icon: 'heroicon-o-clock'),
            ],
        ];
    }

    /**
     * The 担当者 shown at first (legacy rule): sales in the current year and in this or the last month; else everyone
     * with sales in the current year; else everyone.
     *
     * @param  array<int, array<int, array<int, int>>>  $actuals
     * @return list<int>
     */
    private function defaultStaff(array $actuals, int $current): array
    {
        $now = now();
        $last = $now->copy()->subMonthNoOverflow();
        $sold = fn (array $byYear, \DateTimeInterface $day): bool => ($byYear[FiscalYear::startingYear($day)][(int) $day->format('n')] ?? 0) !== 0;

        $recent = array_keys(array_filter($actuals, fn (array $byYear): bool => array_filter($byYear[$current] ?? []) !== [] && ($sold($byYear, $now) || $sold($byYear, $last))));
        if ($recent !== []) {
            return $recent;
        }
        $thisYear = array_keys(array_filter($actuals, fn (array $byYear): bool => array_filter($byYear[$current] ?? []) !== []));

        return $thisYear !== [] ? $thisYear : array_keys($actuals);
    }

    /**
     * @param  array<int, int>  $sums
     * @return array<int, int> every month of the year
     */
    private function months(array $sums): array
    {
        $months = array_fill_keys(FiscalYear::MONTHS, 0);
        foreach ($sums as $month => $amount) {
            $months[$month] = $amount;
        }

        return $months;
    }
}
