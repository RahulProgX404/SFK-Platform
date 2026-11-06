<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use App\Models\User;
use Modules\Analytics\Application\PersonalOverview;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Core\Access\EmployeeDataAccess;

/**
 * 個人の概要 (bug.md 2026-11-06, overview.png): the first row of the dashboard for users without 財務分析を表示 — their
 * own 勤務時間, 収入, 休暇日数 (有給 + the others) and 有給休暇 in the period (PersonalOverview). The same card is shown on
 * a user's page (UserOverviewCard) to whoever may see that person's rates (the person and administrators).
 */
class PersonalOverviewCard extends DashboardCard
{
    protected static ?int $sort = 0;

    protected string $view = 'analytics::widgets.personal-overview';

    public static function canView(): bool
    {
        $user = static::actor();

        return $user !== null && ! $user->seesFinancialAnalysis();
    }

    /** whose figures: the user themselves on the dashboard */
    protected function person(): ?User
    {
        return static::actor();
    }

    protected function overviewPeriod(): Period
    {
        return $this->period();
    }

    /** @return array<string, string>|null the choices of the period when the card has no date button above it */
    protected function ranges(): ?array
    {
        return null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $person = $this->person();
        $shown = $person !== null && EmployeeDataAccess::canViewRates(static::actor(), $person);
        $period = $this->overviewPeriod();

        return [
            'shown' => $shown,
            'figures' => $shown ? app(PersonalOverview::class)->for($person, $period) : null,
            'period' => $period,
            'ranges' => $this->ranges(),
        ];
    }
}
