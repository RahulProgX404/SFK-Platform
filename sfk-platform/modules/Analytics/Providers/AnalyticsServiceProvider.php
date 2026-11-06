<?php

declare(strict_types=1);

namespace Modules\Analytics\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Livewire\Livewire;
use Modules\Analytics\Application\Legacy\LegacyPastSaleImport;
use Modules\Analytics\Application\Legacy\LegacySalesPlanImport;
use Modules\Analytics\Filament\Users\UserOverviewCard;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Filament\Users\UserDetailWidgets;

final class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'analytics';

    protected function registerModule(): void
    {
        // 移行 (legacy:import): 22 過去の売上データ, 25 販売先別売上計画
        $this->app->tag([LegacyPastSaleImport::class, LegacySalesPlanImport::class], LegacyImporter::TAG);
    }

    protected function bootModule(): void
    {
        // 個人の概要 on the page of a user (ユーザー管理, 02. 従業員マスタ): for the person and administrators (作業単価)
        Livewire::component('analytics.user-overview-card', UserOverviewCard::class);
        $this->app->make(UserDetailWidgets::class)->add(
            UserOverviewCard::class,
            fn (User $viewer, User $person): bool => EmployeeDataAccess::canViewRates($viewer, $person),
        );
    }
}
