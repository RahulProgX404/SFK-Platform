<?php

declare(strict_types=1);

namespace Modules\Analytics\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Analytics\Application\Legacy\LegacyPastSaleImport;
use Modules\Analytics\Application\Legacy\LegacySalesPlanImport;
use Modules\Core\Application\Legacy\LegacyImporter;

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
        // Register observers, policies, event listeners here.
    }
}
