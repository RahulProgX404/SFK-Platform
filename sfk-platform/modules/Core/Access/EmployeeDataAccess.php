<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;

/**
 * Who may see the employee-master data on a user (SYSTEM_BRAIN APP_02 従業員マスタ: 担当者コード,
 * 作業員コード, 部課コード, 作業単価, 従業員カテゴリ; plus 残業単価).
 *
 * - Only administrators (ユーザー種別 super_admin / admin) maintain it — they are the only ones who can
 *   create or edit users at all ({@see UserType::manageableTypes()}).
 * - Everyone else may view the codes and the category.
 * - 作業単価 / 残業単価 are confidential cost data (SYSTEM_BRAIN 11_PERMISSIONS §3): administrators see
 *   everyone's, other users only their own.
 */
final class EmployeeDataAccess
{
    /** Confidential hourly rates: visible to administrators and to the user themselves. */
    public const RATE_ATTRIBUTES = ['work_rate', 'overtime_rate'];

    public static function isAdmin(?User $user): bool
    {
        return $user !== null && $user->user_type->isAdministrator();
    }

    public static function canViewRates(?User $viewer, ?User $subject): bool
    {
        if (self::isAdmin($viewer)) {
            return true;
        }

        return $viewer !== null && $subject !== null && $viewer->is($subject);
    }
}
