<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;

final class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'core';

    protected function registerModule(): void
    {
        $this->app->singleton(DocumentNumberGenerator::class);
    }
}
