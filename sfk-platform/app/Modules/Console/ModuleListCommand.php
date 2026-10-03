<?php

declare(strict_types=1);

namespace App\Modules\Console;

use App\Modules\Module;
use App\Modules\ModuleManager;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all modules, their type and whether they are enabled';

    public function handle(ModuleManager $modules): int
    {
        $reasons = $modules->disabledReasons();

        $this->table(
            ['Module', 'Slug', 'Type', 'Version', 'Status', 'Panel', 'Depends on'],
            array_map(fn (Module $m): array => [
                $m->name,
                $m->slug,
                $m->type->value,
                $m->version,
                $modules->isEnabled($m->slug) ? '<info>enabled</info>' : '<comment>disabled</comment> ('.($reasons[$m->slug] ?? '').')',
                $m->ownPanelId() ?? $m->panel ?? '—',
                implode(', ', $m->dependsOn) ?: '—',
            ], array_values($modules->all())),
        );

        return self::SUCCESS;
    }
}
