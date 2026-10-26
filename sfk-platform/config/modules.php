<?php

/*
|--------------------------------------------------------------------------
| Module system
|--------------------------------------------------------------------------
|
| Every folder in `modules/` containing a `module.json` is a module.
|
| Business and extension modules can be switched on/off without code changes:
|
|     MODULE_<SLUG>_ENABLED=false     (slug upper-cased, "-" replaced by "_")
|
| e.g. MODULE_SNS_ENABLED=false. Kernel modules cannot be disabled.
| The value is read here (not in the manager) so `php artisan config:cache` keeps working.
|
*/

$modulesPath = base_path('modules');

// the slugs: from the module cache (php artisan module:cache) when there is one, else from the manifests
$moduleCache = base_path('bootstrap/cache/modules.php');
$slugs = is_file($moduleCache)
    ? array_map(fn (array $module): mixed => $module['manifest']['slug'] ?? null, (array) require $moduleCache)
    : array_map(fn (string $file): mixed => json_decode((string) file_get_contents($file), true)['slug'] ?? null, glob($modulesPath.'/*/module.json') ?: []);

$enabled = [];
foreach ($slugs as $slug) {
    if (! is_string($slug)) {
        continue;
    }

    $value = env('MODULE_'.strtoupper(str_replace('-', '_', $slug)).'_ENABLED');

    if ($value !== null) {
        $enabled[$slug] = filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

return [
    'path' => $modulesPath,

    /** @var array<string, bool> slug => enabled (only modules with an explicit env switch) */
    'enabled' => $enabled,

    /*
    | Slugs that may not be used for modules because they would collide with framework
    | configuration keys (module config is merged under its slug).
    */
    'reserved_slugs' => [
        'app', 'auth', 'broadcasting', 'cache', 'cors', 'database', 'filesystems', 'hashing',
        'logging', 'mail', 'queue', 'sanctum', 'services', 'session', 'view', 'modules',
        'permission', 'activitylog', 'filament', 'livewire', 'api', 'admin', 'login',
    ],
];
