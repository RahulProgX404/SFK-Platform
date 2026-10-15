<?php

declare(strict_types=1);

namespace App\Modules\Console;

use App\Modules\ModuleManager;
use App\Modules\ModuleType;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a new module from stubs/module/<type>/.
 *
 *   php artisan make:module Sales                         business module in the ERP panel
 *   php artisan make:module Sns --type=extension          own panel at /sns + /api/sns
 */
final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : StudlyCase module name, e.g. Sales, MasterData, Sns, SeoAnalyzer}
        {--type=business : business | extension}
        {--slug= : URL/config slug (default: kebab-case of the name)}
        {--panel=erp : Host panel for business modules}
        {--description= : Short description for module.json}';

    protected $description = 'Create a new module (business feature or extension product)';

    public function handle(ModuleManager $modules, Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $type = ModuleType::tryFrom((string) $this->option('type'));
        $slug = (string) ($this->option('slug') ?: Str::kebab($name));

        if ($type === null || $type === ModuleType::Kernel) {
            $this->error('--type must be "business" or "extension".');

            return self::FAILURE;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->error('Name must be StudlyCase (letters/digits, starting with a capital), e.g. "SeoAnalyzer".');

            return self::FAILURE;
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/', $slug) !== 1) {
            $this->error('Slug must be kebab-case, e.g. "seo-analyzer".');

            return self::FAILURE;
        }

        if (in_array($slug, (array) config('modules.reserved_slugs', []), true)) {
            $this->error("Slug [{$slug}] is reserved.");

            return self::FAILURE;
        }

        $target = $modules->modulesPath().DIRECTORY_SEPARATOR.$name;

        if ($modules->find($slug) !== null || $files->exists($target)) {
            $this->error("Module [{$name}] / slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        $stubs = base_path('stubs/module/'.$type->value);
        $replacements = [
            '{{ name }}' => $name,
            '{{ slug }}' => $slug,
            '{{ SLUG_ENV }}' => strtoupper(str_replace('-', '_', $slug)),
            '{{ title }}' => Str::headline($name),
            '{{ panel }}' => (string) $this->option('panel'),
            '{{ description }}' => (string) ($this->option('description') ?: Str::headline($name).' module'),
        ];

        foreach ($files->allFiles($stubs, true) as $stub) {
            $relative = str_replace(array_keys($replacements), array_values($replacements), $stub->getRelativePathname());
            $relative = preg_replace('/\.stub$/', '', $relative) ?? $relative;
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            $files->ensureDirectoryExists(dirname($destination));
            $files->put($destination, str_replace(array_keys($replacements), array_values($replacements), $stub->getContents()));
        }

        $this->components->info("Module [{$name}] created in modules/{$name} ({$type->value}).");
        $this->line('Next steps:');
        $this->line('  1. php artisan migrate');
        if ($type === ModuleType::Extension) {
            $this->line("  2. 共通管理: allow the new dashboard on the 従業員カテゴリ (or users) who should use /{$slug}");
        } else {
            $this->line('  2. add Filament resources under modules/'.$name.'/Filament/Resources — they appear in the "'.$this->option('panel').'" panel');
        }
        $this->line('  Toggle with MODULE_'.$replacements['{{ SLUG_ENV }}'].'_ENABLED=false');

        return self::SUCCESS;
    }
}
