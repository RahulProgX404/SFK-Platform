<?php

declare(strict_types=1);

namespace App\Modules\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ReflectionClass;

/**
 * Base Filament plugin for business modules. Place the plugin class in `modules/<Name>/Filament/`
 * and it auto-discovers `Resources/`, `Pages/` and `Widgets/` next to it, adding them to the host
 * panel (declared as "panel" in module.json, normally "erp").
 */
abstract class ModulePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $reflection = new ReflectionClass(static::class);
        $directory = dirname((string) $reflection->getFileName());
        $namespace = $reflection->getNamespaceName();

        if (is_dir($directory.'/Resources')) {
            $panel->discoverResources(in: $directory.'/Resources', for: $namespace.'\\Resources');
        }

        if (is_dir($directory.'/Pages')) {
            $panel->discoverPages(in: $directory.'/Pages', for: $namespace.'\\Pages');
        }

        if (is_dir($directory.'/Widgets')) {
            $panel->discoverWidgets(in: $directory.'/Widgets', for: $namespace.'\\Widgets');
        }

        $this->registerExtra($panel);
    }

    public function boot(Panel $panel): void {}

    /** Hook for explicit registrations (render hooks, navigation items, …). */
    protected function registerExtra(Panel $panel): void {}
}
