<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\Organization;
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
            Stat::make('組織', number_format(Organization::query()->where('is_active', true)->count()).' 組織')
                ->description('有効な組織の数')
                ->icon(Heroicon::OutlinedBuildingOffice2),
            Stat::make('パスワード変更待ち', number_format($pending).' 名')
                ->description($pending > 0 ? '初回ログイン／再設定後に未変更' : '対応待ちはありません')
                ->icon(Heroicon::OutlinedKey)
                ->color($pending > 0 ? 'warning' : 'gray'),
        ];
    }
}
