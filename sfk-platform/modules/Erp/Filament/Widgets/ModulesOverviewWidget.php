<?php

declare(strict_types=1);

namespace Modules\Erp\Filament\Widgets;

use App\Modules\Module;
use App\Modules\ModuleManager;
use Filament\Widgets\Widget;
use Modules\Core\Access\Roles;

/**
 * Dashboard card listing installed modules and their status. Super-admins only.
 */
final class ModulesOverviewWidget extends Widget
{
    protected static ?int $sort = 10;

    /** Cheap to render (reads module.json files) — render inline instead of lazy-loading. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'erp::widgets.modules-overview';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole(Roles::SUPER_ADMIN);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $manager = app(ModuleManager::class);
        $reasons = $manager->disabledReasons();

        return [
            'modules' => array_map(fn (Module $module): array => [
                'name' => $module->name,
                'slug' => $module->slug,
                'type' => $module->type->value,
                'version' => $module->version,
                'description' => $module->description,
                'enabled' => $manager->isEnabled($module->slug),
                'reason' => $reasons[$module->slug] ?? null,
            ], array_values($manager->all())),
        ];
    }
}
