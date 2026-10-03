<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * Role names. Business roles (accounting admin, sales, production, approver …) are added by the
 * modules that need them; see SYSTEM_BRAIN 11_PERMISSIONS for the evidence-based role model.
 */
final class Roles
{
    /** Full access to every panel and action. */
    public const SUPER_ADMIN = 'super-admin';
}
