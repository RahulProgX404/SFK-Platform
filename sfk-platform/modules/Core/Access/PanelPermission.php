<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * Naming convention for "may open this Filament panel" permissions: `panel.<panel-id>.access`.
 * The ERP panel id is "erp"; every extension module's panel id is its slug (e.g. "sns", "seo").
 */
final class PanelPermission
{
    public static function for(string $panelId): string
    {
        return "panel.{$panelId}.access";
    }
}
