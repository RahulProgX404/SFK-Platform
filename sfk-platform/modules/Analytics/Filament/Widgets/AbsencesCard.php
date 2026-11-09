<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\TeamDashboard;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;

/**
 * 休暇・勤怠予定 (row 3, every user; design_ref Recent Transactions): who is off — or late / leaving early — today and
 * tomorrow, from 16. 申請管理 (申請中 and 承認済), as far as the user may see the requests.
 */
final class AbsencesCard extends DashboardCard
{
    protected static ?int $sort = 8;

    protected string $view = 'analytics::widgets.absences';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $today = $this->today();

        return [
            'days' => app(TeamDashboard::class)->absences($today),
            'dates' => ['today' => $today, 'tomorrow' => $today->modify('+1 day')],
            'requestsUrl' => self::appUrl(LeaveRequestResource::appCode()),
            'span' => $this->teamCardSpan(),
        ];
    }
}
