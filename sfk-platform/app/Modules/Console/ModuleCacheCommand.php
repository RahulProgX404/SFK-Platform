<?php

declare(strict_types=1);

namespace App\Modules\Console;

use App\Modules\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/** Writes the module cache (modules, manifests and their parts), so requests no longer read the module folders. */
final class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Cache the modules (manifests and conventional folders) for faster requests';

    public function handle(ModuleManager $modules, Filesystem $files): int
    {
        $path = $modules->cachePath() ?? $this->laravel->bootstrapPath('cache/modules.php');

        $files->put($path, '<?php return '.var_export($modules->export(), true).';'.PHP_EOL);
        $this->components->info('Modules cached successfully.');

        return self::SUCCESS;
    }
}
