<?php

declare(strict_types=1);

namespace Modules\HumanResources\Providers;

use App\Modules\ModuleServiceProvider;

final class HumanResourcesServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'hr';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // Register observers, policies, event listeners here.
    }
}
