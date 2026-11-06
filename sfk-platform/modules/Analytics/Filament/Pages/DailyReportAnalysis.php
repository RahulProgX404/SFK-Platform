<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Pages;

use Filament\Pages\Page;
use Modules\Analytics\Application\DailyReportRanking;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Filament\Pages\Concerns\HasAnalysisChrome;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;

/**
 * 分析 → 日報分析 (/erp/analysis/daily-reports; legacy DailyReportAnalysis.bundle.js), not an app yet: per 作業者 and month
 * the amount made (枚数単価 + 型替え単価 of 08. 日報入力) and the hours worked (作業時間 + 残業時間), ranked by 年間合計. For
 * everyone who may view 08. 日報入力 (the dashboard's 作業者 card leads here and is shown to all, user 2026-11-05); the
 * figures follow what the user may see of the reports.
 */
final class DailyReportAnalysis extends Page
{
    use HasAnalysisChrome;

    protected static ?string $title = '日報分析';

    protected static ?string $slug = 'analysis/daily-reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'analytics::filament.analysis-daily';

    public ?int $year = null;

    public static function canAccess(): bool
    {
        return DailyReportResource::canViewAny();
    }

    public function mount(): void
    {
        $this->year = FiscalYear::startingYear(now());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $current = FiscalYear::startingYear(now());
        $service = app(DailyReportRanking::class);
        $available = $service->years();
        $year = in_array($this->year, $available, true) ? (int) $this->year : (int) end($available);
        $ranking = $service->ranking($year);
        $total = array_sum(array_column($ranking, 'total'));
        $hours = round(array_sum(array_column($ranking, 'total_hours')), 2);

        $totals = array_fill_keys(FiscalYear::MONTHS, 0);
        $totalHours = array_fill_keys(FiscalYear::MONTHS, 0.0);
        foreach ($ranking as $row) {
            foreach (FiscalYear::MONTHS as $month) {
                $totals[$month] += $row['months'][$month];
                $totalHours[$month] += $row['hours'][$month];
            }
        }

        return [
            'months' => FiscalYear::MONTHS,
            'currentYear' => $current,
            'futureFrom' => array_search((int) now()->format('n'), FiscalYear::MONTHS, true) + 1,
            'daily' => [
                'available' => $available,
                'year' => $year,
                'ranking' => $ranking,
                'totals' => $totals,
                'totalHours' => $totalHours,
                'cards' => [
                    SummaryCard::make('年間合計', $total),
                    ['label' => '総作業時間', 'value' => self::hours($hours), 'tone' => 'purple', 'icon' => 'heroicon-o-clock'],
                    ['label' => '作業者', 'value' => count($ranking).'名', 'tone' => 'slate', 'icon' => 'heroicon-o-users'],
                    ['label' => '1位', 'value' => $ranking[0]['name'] ?? '—', 'tone' => 'amber', 'icon' => 'heroicon-o-trophy'],
                    SummaryCard::make('1時間あたり', $hours > 0 ? (int) floor($total / $hours) : null, tone: 'green', icon: 'heroicon-o-banknotes'),
                ],
            ],
        ];
    }

    /** 「12.5時間」 */
    public static function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2), '0'), '.').'時間';
    }
}
