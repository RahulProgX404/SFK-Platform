<?php

declare(strict_types=1);

namespace App\Modules\Console;

use App\Modules\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/** Removes the module cache: modules are read from their folders again. */
final class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Remove the module cache';

    public function handle(ModuleManager $modules, Filesystem $files): int
    {
        if (($path = $modules->cachePath()) !== null) {
            $files->delete($path);
        }
        $this->components->info('Module cache cleared successfully.');

        return self::SUCCESS;
    }
}
