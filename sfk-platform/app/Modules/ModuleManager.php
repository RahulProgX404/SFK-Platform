<?php

declare(strict_types=1);

namespace App\Modules;

use RuntimeException;

/**
 * Discovers modules, decides which are enabled and returns them in dependency order.
 *
 * A module is enabled when:
 *  1. it is a kernel module (always), or its switch is on — `config('modules.enabled.<slug>')`
 *     (env MODULE_<SLUG>_ENABLED) overrides the manifest's "enabled" default; and
 *  2. every module listed in "depends_on" exists and is enabled.
 *
 * With a cache file (`php artisan module:cache`, also run by `php artisan optimize`) the modules are read from it:
 * no module.json is opened and no folder is checked on a request.
 */
final class ModuleManager
{
    /** @var array<string, Module>|null */
    private ?array $modules = null;

    /** @var array<string, Module>|null enabled modules, dependency-ordered */
    private ?array $enabled = null;

    /** @var array<string, string> slug => reason */
    private array $disabledReasons = [];

    /**
     * @param  array<string, bool>  $overrides  slug => enabled
     * @param  string|null  $cachePath  the cached modules (export()), used when the file exists
     */
    public function __construct(
        private readonly string $modulesPath,
        private readonly array $overrides = [],
        private readonly ?string $cachePath = null,
    ) {}

    /** @return array<string, Module> all discovered modules keyed by slug */
    public function all(): array
    {
        return $this->modules ??= $this->discover();
    }

    /** @return array<string, Module> enabled modules in boot order (dependencies first) */
    public function enabled(): array
    {
        if ($this->enabled === null) {
            $this->resolve();
        }

        return $this->enabled ?? [];
    }

    public function find(string $slug): ?Module
    {
        return $this->all()[$slug] ?? null;
    }

    public function get(string $slug): Module
    {
        return $this->find($slug) ?? throw new RuntimeException("Module [{$slug}] does not exist.");
    }

    public function isEnabled(string $slug): bool
    {
        return isset($this->enabled()[$slug]);
    }

    /** @return array<string, Module> enabled business modules that plug into the given panel */
    public function forPanel(string $panelId): array
    {
        return array_filter(
            $this->enabled(),
            fn (Module $m): bool => $m->type === ModuleType::Business && $m->panel === $panelId,
        );
    }

    /** @return array<string, Module> */
    public function ofType(ModuleType $type): array
    {
        return array_filter($this->enabled(), fn (Module $m): bool => $m->type === $type);
    }

    /** @return array<string, string> slug => why the module is not enabled */
    public function disabledReasons(): array
    {
        $this->enabled();

        return $this->disabledReasons;
    }

    public function modulesPath(): string
    {
        return $this->modulesPath;
    }

    public function cachePath(): ?string
    {
        return $this->cachePath;
    }

    /**
     * Every module read from the module folders (not from the cache), as the cache file stores them.
     *
     * @return list<array{manifest: array<string, mixed>, path: string, parts: list<string>}>
     */
    public function export(): array
    {
        return array_values(array_map(fn (Module $module): array => $module->toCache(), $this->scan()));
    }

    /** @return array<string, Module> */
    private function discover(): array
    {
        if ($this->cachePath !== null && is_file($this->cachePath)) {
            $modules = [];
            foreach ((array) require $this->cachePath as $cached) {
                $module = Module::fromManifest($cached['manifest'], $cached['path'], $cached['parts']);
                $modules[$module->slug] = $module;
            }

            return $modules;
        }

        return $this->scan();
    }

    /** @return array<string, Module> */
    private function scan(): array
    {
        $modules = [];

        foreach (glob($this->modulesPath.'/*/module.json') ?: [] as $file) {
            $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $module = Module::fromManifest($manifest, dirname($file));

            if (isset($modules[$module->slug])) {
                throw new RuntimeException("Duplicate module slug [{$module->slug}].");
            }

            $modules[$module->slug] = $module;
        }

        uasort($modules, fn (Module $a, Module $b): int => [$a->order, $a->name] <=> [$b->order, $b->name]);

        return $modules;
    }

    private function resolve(): void
    {
        $all = $this->all();
        $this->disabledReasons = [];

        // 1. switches
        $candidates = [];
        foreach ($all as $slug => $module) {
            $on = $module->type->canBeDisabled()
                ? ($this->overrides[$slug] ?? $module->enabledByDefault)
                : true;

            if ($on) {
                $candidates[$slug] = $module;
            } else {
                $this->disabledReasons[$slug] = 'switched off';
            }
        }

        // 2. dependencies (repeat until stable so transitive failures propagate)
        do {
            $changed = false;
            foreach ($candidates as $slug => $module) {
                foreach ($module->dependsOn as $dependency) {
                    if (! isset($candidates[$dependency])) {
                        if (! $module->type->canBeDisabled()) {
                            throw new RuntimeException("Kernel module [{$slug}] depends on missing module [{$dependency}].");
                        }
                        unset($candidates[$slug]);
                        $this->disabledReasons[$slug] = isset($all[$dependency])
                            ? "dependency [{$dependency}] is disabled"
                            : "dependency [{$dependency}] does not exist";
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed);

        // 3. topological order (stable by manifest order)
        $ordered = [];
        $visiting = [];
        $visit = function (string $slug) use (&$visit, &$ordered, &$visiting, $candidates): void {
            if (isset($ordered[$slug])) {
                return;
            }
            if (isset($visiting[$slug])) {
                throw new RuntimeException("Circular module dependency involving [{$slug}].");
            }
            $visiting[$slug] = true;
            foreach ($candidates[$slug]->dependsOn as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$slug]);
            $ordered[$slug] = $candidates[$slug];
        };

        foreach (array_keys($candidates) as $slug) {
            $visit($slug);
        }

        $this->enabled = $ordered;
    }
}
