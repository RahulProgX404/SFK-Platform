<?php

declare(strict_types=1);

namespace App\Modules;

use InvalidArgumentException;

/**
 * Immutable description of one module, read from `modules/<Name>/module.json`.
 */
final readonly class Module
{
    /**
     * @param  list<string>  $dependsOn  slugs of modules that must be enabled first
     * @param  class-string|null  $provider
     * @param  class-string|null  $filamentPlugin  business modules: plugin added to the host panel
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ModuleType $type,
        public string $path,
        public string $description = '',
        public string $version = '0.1.0',
        public ?string $provider = null,
        public ?string $filamentPlugin = null,
        public ?string $panel = null,
        public array $dependsOn = [],
        public int $order = 100,
        public bool $enabledByDefault = true,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(array $manifest, string $path): self
    {
        foreach (['name', 'slug', 'type'] as $required) {
            if (! isset($manifest[$required]) || ! is_string($manifest[$required]) || $manifest[$required] === '') {
                throw new InvalidArgumentException("Module manifest [{$path}/module.json] is missing \"{$required}\".");
            }
        }

        $type = ModuleType::tryFrom($manifest['type'])
            ?? throw new InvalidArgumentException("Module [{$manifest['slug']}] has unknown type \"{$manifest['type']}\".");

        return new self(
            name: $manifest['name'],
            slug: $manifest['slug'],
            type: $type,
            path: $path,
            description: (string) ($manifest['description'] ?? ''),
            version: (string) ($manifest['version'] ?? '0.1.0'),
            provider: $manifest['provider'] ?? null,
            filamentPlugin: $manifest['filament_plugin'] ?? null,
            panel: $manifest['panel'] ?? null,
            dependsOn: array_values(array_map('strval', $manifest['depends_on'] ?? [])),
            order: (int) ($manifest['order'] ?? 100),
            enabledByDefault: (bool) ($manifest['enabled'] ?? true),
        );
    }

    /** Namespace root of the module, e.g. `Modules\Sales`. */
    public function namespace(): string
    {
        return 'Modules\\'.$this->name;
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    /** Filament panel id owned by an extension module (the slug), or null. */
    public function ownPanelId(): ?string
    {
        return $this->type === ModuleType::Extension ? $this->slug : null;
    }
}
