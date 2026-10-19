<?php

declare(strict_types=1);

namespace Modules\Orders\Filament;

use App\Modules\Filament\ModulePlugin;

/**
 * Adds this module's Filament Resources/, Pages/ and Widgets/ to the "erp" panel.
 */
final class OrdersPlugin extends ModulePlugin
{
    public function getId(): string
    {
        return 'orders';
    }
}
