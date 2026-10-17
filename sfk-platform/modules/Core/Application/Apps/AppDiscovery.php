<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use FilesystemIterator;
use Modules\Core\Filament\AppResource;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Finds the apps that exist in code: every concrete AppResource under a module's `Filament/` folder, keyed by
 * its appCode(). Disabled modules are included on purpose — switching a module off must not remove its apps
 * (and their permissions) from the registry; only deleting the code does.
 */
final class AppDiscovery
{
    /**
     * @param  list<string>  $modulesPaths  folders holding `<Module>/Filament/…`
     * @param  array<string, string>|null  $fixed  code => name, instead of scanning (tests)
     */
    public function __construct(
        private readonly array $modulesPaths = [],
        private readonly ?array $fixed = null,
    ) {}

    /** @param  array<string, string>  $definitions  code => name */
    public static function fixed(array $definitions): self
    {
        return new self(fixed: $definitions);
    }

    /** @return array<string, string> code => name */
    public function definitions(): array
    {
        if ($this->fixed !== null) {
            return $this->fixed;
        }

        $definitions = [];
        foreach ($this->resourceClasses() as $class) {
            $definitions[$class::appCode()] ??= $class::appName();
        }

        return $definitions;
    }

    /**
     * The その他の設定 each app starts with, where it differs from the defaults (AppResource::appSettingDefaults()).
     *
     * @return array<string, array<string, bool>> code => setting => on
     */
    public function settingDefaults(): array
    {
        if ($this->fixed !== null) {
            return [];
        }

        $defaults = [];
        foreach ($this->resourceClasses() as $class) {
            $defaults[$class::appCode()] ??= $class::appSettingDefaults();
        }

        return array_filter($defaults);
    }

    /** @return list<class-string<AppResource>> */
    private function resourceClasses(): array
    {
        $classes = [];

        foreach ($this->modulesPaths as $modulesPath) {
            foreach (glob(rtrim($modulesPath, '/\\').'/*/Filament', GLOB_ONLYDIR) ?: [] as $directory) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    $class = $file->getExtension() === 'php' ? self::classIn($file->getPathname()) : null;

                    if ($class !== null
                        && class_exists($class)
                        && is_subclass_of($class, AppResource::class)
                        && ! (new ReflectionClass($class))->isAbstract()) {
                        $classes[] = $class;
                    }
                }
            }
        }

        return $classes;
    }

    /** Fully qualified name of the class declared in a PHP file, if any. */
    private static function classIn(string $path): ?string
    {
        $source = (string) file_get_contents($path);

        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $source, $class) !== 1) {
            return null;
        }

        return $namespace[1].'\\'.$class[1];
    }
}
