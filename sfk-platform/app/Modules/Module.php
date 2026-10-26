<?php

declare(strict_types=1);

namespace App\Modules;

use InvalidArgumentException;

/**
 * Immutable description of one module, read from `modules/<Name>/module.json`, with the conventional files and folders
 * it has (PARTS): the module's provider and Filament plugin wire only those, without looking at the disk on every
 * request (ModuleManager keeps them in its cache, `php artisan module:cache`).
 */
final readonly class Module
{
    /** Conventional files and folders a module may have (relative to its folder). */
    public const PARTS = [
        'Config/config.php',
        'Database/Migrations',
        'Resources/views',
        'Resources/lang',
        'routes/web.php',
        'routes/api.php',
        'Filament/Resources',
        'Filament/Pages',
        'Filament/Widgets',
    ];

    /**
     * @param  list<string>  $dependsOn  slugs of modules that must be enabled first
     * @param  class-string|null  $provider
     * @param  class-string|null  $filamentPlugin  business modules: plugin added to the host panel
     * @param  list<string>  $parts  the PARTS the module has
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
        public array $parts = [],
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>|null  $parts  the PARTS the module has; null = look in its folder
     */
    public static function fromManifest(array $manifest, string $path, ?array $parts = null): self
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
            parts: $parts ?? self::partsIn($path),
        );
    }

    /**
     * The module as it is cached (ModuleManager::export()): its manifest, folder and parts.
     *
     * @return array{manifest: array<string, mixed>, path: string, parts: list<string>}
     */
    public function toCache(): array
    {
        return [
            'manifest' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'type' => $this->type->value,
                'description' => $this->description,
                'version' => $this->version,
                'provider' => $this->provider,
                'filament_plugin' => $this->filamentPlugin,
                'panel' => $this->panel,
                'depends_on' => $this->dependsOn,
                'order' => $this->order,
                'enabled' => $this->enabledByDefault,
            ],
            'path' => $this->path,
            'parts' => $this->parts,
        ];
    }

    /** Whether the module has one of the PARTS (e.g. 'routes/web.php'). */
    public function has(string $part): bool
    {
        return in_array($part, $this->parts, true);
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

    /** @return list<string> the PARTS present in a module folder */
    private static function partsIn(string $path): array
    {
        return array_values(array_filter(self::PARTS, fn (string $part): bool => file_exists($path.DIRECTORY_SEPARATOR.$part)));
    }

    /** Filament panel id owned by an extension module (the slug), or null. */
    public function ownPanelId(): ?string
    {
        return $this->type === ModuleType::Extension ? $this->slug : null;
    }
}
