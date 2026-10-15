<?php

declare(strict_types=1);

namespace Modules\MasterData\Providers;

use App\Modules\ModuleServiceProvider;

final class MasterDataServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'master-data';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // Register observers, policies, event listeners here.
    }
}
