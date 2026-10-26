<?php

declare(strict_types=1);

namespace App\Modules\Filament;

use App\Modules\ModuleManager;
use Closure;
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
        // the module's folders are known (Module::$parts, cached by module:cache); a plugin of no module checks the disk
        $has = $this->moduleParts() ?? fn (string $folder): bool => is_dir($directory.'/'.$folder);

        if ($has('Resources')) {
            $panel->discoverResources(in: $directory.'/Resources', for: $namespace.'\\Resources');
        }

        if ($has('Pages')) {
            $panel->discoverPages(in: $directory.'/Pages', for: $namespace.'\\Pages');
        }

        if ($has('Widgets')) {
            $panel->discoverWidgets(in: $directory.'/Widgets', for: $namespace.'\\Widgets');
        }

        $this->registerExtra($panel);
    }

    /** @return (Closure(string): bool)|null whether the plugin's module has Filament/<folder> */
    private function moduleParts(): ?Closure
    {
        foreach (app(ModuleManager::class)->all() as $module) {
            if ($module->filamentPlugin === static::class) {
                return fn (string $folder): bool => $module->has('Filament/'.$folder);
            }
        }

        return null;
    }

    public function boot(Panel $panel): void {}

    /** Hook for explicit registrations (render hooks, navigation items, …). */
    protected function registerExtra(Panel $panel): void {}
}
