<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * ユーザー種別: how a user uses this system. Not to be confused with 従業員カテゴリ, the person's
 * position/duty in the company (営業, 製造 …), which grants no system rights.
 *
 * - super_admin: owned by the environment (core:super-admin), manages admins and employees.
 * - admin: manages employees, including their employee-master data (SYSTEM_BRAIN APP_02).
 * - employee: uses the dashboards granted to them or their 従業員カテゴリ; no access to 共通管理.
 */
enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'スーパー管理者',
            self::Admin => '管理者',
            self::Employee => '従業員',
        };
    }

    public function isAdministrator(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }

    /**
     * Types this type may create, edit and delete: super admins manage admins and employees, admins
     * manage employees. Nobody manages super admins (they come from the environment).
     *
     * @return list<self>
     */
    public function manageableTypes(): array
    {
        return match ($this) {
            self::SuperAdmin => [self::Admin, self::Employee],
            self::Admin => [self::Employee],
            self::Employee => [],
        };
    }

    public function canManage(self $type): bool
    {
        return in_array($type, $this->manageableTypes(), true);
    }

    /**
     * @param  list<self>|null  $types  defaults to all types
     * @return array<string, string> value => label
     */
    public static function options(?array $types = null): array
    {
        $options = [];
        foreach ($types ?? self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
