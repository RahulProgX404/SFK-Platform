<?php

declare(strict_types=1);

namespace Modules\Analytics\Providers;

use App\Modules\ModuleServiceProvider;

final class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'analytics';

    protected function registerModule(): void
    {
        // Bind module services / repositories here.
    }

    protected function bootModule(): void
    {
        // Register observers, policies, event listeners here.
    }
}
