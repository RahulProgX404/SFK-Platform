<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\EmployeeCategory;
use Modules\Settings\Application\UserStatistics;

/** Key figures of the platform on the settings dashboard. */
final class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $statistics = app(UserStatistics::class);
        $pending = $statistics->pendingPasswordChange();

        return [
            Stat::make('登録ユーザー', number_format($statistics->total()).' 名')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('在籍中', number_format($statistics->withStatus(EmploymentStatus::Active)).' 名')
                ->icon(Heroicon::OutlinedIdentification)
                ->color('success'),
            Stat::make('従業員カテゴリ', number_format(EmployeeCategory::query()->where('is_active', true)->count()).' カテゴリ')
                ->icon(Heroicon::OutlinedTag),
            Stat::make('パスワード変更待ち', number_format($pending).' 名')
                ->icon(Heroicon::OutlinedKey)
                ->color($pending > 0 ? 'warning' : 'gray'),
        ];
    }
}
