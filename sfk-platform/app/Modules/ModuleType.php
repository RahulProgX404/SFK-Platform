<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * The three kinds of modules the platform supports.
 *
 * - Kernel:    foundation code every other module may depend on (shared kernel, main panel shell).
 *              Always enabled.
 * - Business:  ERP features (sales, purchasing, production…). They plug their Filament
 *              resources into a host panel (normally "erp") and may depend on each other.
 * - Extension: secondary products that live in the same codebase but are used *outside* the ERP
 *              (e.g. SNS management, SEO analyzer). Each gets its own Filament panel (own URL or
 *              domain, own access permission) and may expose its own API.
 */
enum ModuleType: string
{
    case Kernel = 'kernel';
    case Business = 'business';
    case Extension = 'extension';

    public function canBeDisabled(): bool
    {
        return $this !== self::Kernel;
    }
}
