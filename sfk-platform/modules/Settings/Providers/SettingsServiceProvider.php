<?php

declare(strict_types=1);

namespace Modules\Settings\Providers;

use App\Modules\ModuleServiceProvider;
use Modules\Settings\Filament\SettingsPanelProvider;

final class SettingsServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'settings';

    protected function registerModule(): void
    {
        // The extension's own Filament panel (/settings or its own domain).
        $this->app->register(SettingsPanelProvider::class);
    }

    protected function bootModule(): void
    {
        // Schedules, event listeners, external API clients …
    }
}
