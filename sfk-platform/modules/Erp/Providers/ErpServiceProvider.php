<?php

declare(strict_types=1);

namespace Modules\Erp\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Erp\Filament\ErpPanelProvider;

final class ErpServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'erp';

    protected function registerModule(): void
    {
        $this->app->register(ErpPanelProvider::class);
    }
}
