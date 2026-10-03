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
        $config = $this->module()->path('Config/config.php');
        if (is_file($config)) {
            $this->mergeConfigFrom($config, $this->slug);
        }

        $this->registerModule();
    }

    final public function boot(): void
    {
        $module = $this->module();

        if (is_dir($migrations = $module->path('Database/Migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($views = $module->path('Resources/views'))) {
            $this->loadViewsFrom($views, $this->slug);
        }

        if (is_dir($lang = $module->path('Resources/lang'))) {
            $this->loadTranslationsFrom($lang, $this->slug);
        }

        if (! $this->app->routesAreCached()) {
            if (is_file($web = $module->path('routes/web.php'))) {
                Route::middleware('web')->group($web);
            }

            if (is_file($api = $module->path('routes/api.php'))) {
                Route::middleware('api')
                    ->prefix('api/'.$this->slug)
                    ->name($this->slug.'.api.')
                    ->group($api);
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
