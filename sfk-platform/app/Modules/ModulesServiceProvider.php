<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Console\MakeModuleCommand;
use App\Modules\Console\ModuleCacheCommand;
use App\Modules\Console\ModuleClearCommand;
use App\Modules\Console\ModuleListCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the module system: builds the ModuleManager and registers the service provider
 * of every enabled module in dependency order. `php artisan optimize` also caches the modules (module:cache).
 */
final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn (): ModuleManager => new ModuleManager(
            modulesPath: (string) config('modules.path', base_path('modules')),
            overrides: (array) config('modules.enabled', []),
            cachePath: $this->app->bootstrapPath('cache/modules.php'),
        ));

        foreach ($this->app->make(ModuleManager::class)->enabled() as $module) {
            if ($module->provider !== null) {
                $this->app->register($module->provider);
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                MakeModuleCommand::class,
                ModuleCacheCommand::class,
                ModuleClearCommand::class,
            ]);

            $this->optimizes(optimize: 'module:cache', clear: 'module:clear', key: 'modules');
        }
    }
}
