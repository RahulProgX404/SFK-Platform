<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament;

use App\Modules\Filament\ModulePlugin;

/**
 * Adds this module's Filament Resources/, Pages/ and Widgets/ to the "erp" panel.
 */
final class MasterDataPlugin extends ModulePlugin
{
    public function getId(): string
    {
        return 'master-data';
    }
}
