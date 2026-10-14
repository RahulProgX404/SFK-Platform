<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * Role names. Business roles (accounting admin, sales, production, approver …) are typed by the
 * administrator in user management; see SYSTEM_BRAIN 11_PERMISSIONS for the evidence-based role model.
 */
final class Roles
{
    /** Full access to every panel and action. Granted only from the environment (core.super_admin). */
    public const SUPER_ADMIN = 'super-admin';

    /** Role names that can never be assigned through user management. */
    public const RESERVED = [self::SUPER_ADMIN];

    public static function isReserved(string $role): bool
    {
        return in_array(mb_strtolower(trim($role)), self::RESERVED, true);
    }
}
