<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base service provider for every module. It wires the module's conventional folders:
 *
 *   Config/config.php        → merged as config('<slug>.*')
 *   Database/Migrations/     → loaded migrations
 *   routes/web.php           → "web" middleware group
 *   routes/api.php           → "api" middleware group, prefix /api/<slug>, names "<slug>.api."
 *   Resources/views/         → view namespace "<slug>::"
 *   Resources/lang/          → translation namespace "<slug>::"
 *
 * Subclasses set $slug and override registerModule()/bootModule() for module-specific wiring.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Module slug as declared in module.json. */
    protected string $slug;

    /** @var list<class-string> Artisan commands provided by the module. */
    protected array $commands = [];

    final public function register(): void
    {
        $module = $this->module();
        if ($module->has('Config/config.php')) {
            $this->mergeConfigFrom($module->path('Config/config.php'), $this->slug);
        }

        $this->registerModule();
    }

    final public function boot(): void
    {
        $module = $this->module();

        // the module's parts are known (Module::$parts, cached by module:cache): no look at the disk per request
        if ($module->has('Database/Migrations')) {
            $this->loadMigrationsFrom($module->path('Database/Migrations'));
        }

        if ($module->has('Resources/views')) {
            $this->loadViewsFrom($module->path('Resources/views'), $this->slug);
        }

        if ($module->has('Resources/lang')) {
            $this->loadTranslationsFrom($module->path('Resources/lang'), $this->slug);
        }

        if (! $this->app->routesAreCached()) {
            if ($module->has('routes/web.php')) {
                Route::middleware('web')->group($module->path('routes/web.php'));
            }

            if ($module->has('routes/api.php')) {
                Route::middleware('api')
                    ->prefix('api/'.$this->slug)
                    ->name($this->slug.'.api.')
                    ->group($module->path('routes/api.php'));
            }
        }

        if ($this->commands !== [] && $this->app->runningInConsole()) {
            $this->commands($this->commands);
        }

        $this->bootModule();
    }

    protected function registerModule(): void {}

    protected function bootModule(): void {}

    protected function module(): Module
    {
        return $this->app->make(ModuleManager::class)->get($this->slug);
    }
}
