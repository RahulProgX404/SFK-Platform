<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Settings\Application\UserStatistics;

/** Headline figures of the user analysis (ユーザー分析). */
final class UserStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $statistics = app(UserStatistics::class);
        $total = $statistics->total();
        $active = $statistics->withStatus(EmploymentStatus::Active);
        $onLeave = $statistics->withStatus(EmploymentStatus::OnLeave);
        $monthly = $statistics->monthlyRegistrations(6);
        $newThisMonth = $monthly[array_key_last($monthly)];

        return [
            Stat::make('登録ユーザー', number_format($total).' 名')
                ->chart(array_map(floatval(...), $monthly))
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('在籍中', number_format($active).' 名')
                ->icon(Heroicon::OutlinedIdentification),
        ];
    }
}
