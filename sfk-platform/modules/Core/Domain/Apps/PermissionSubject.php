<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Apps;

/**
 * Who an app permission row applies to. A user's own row overrides their 従業員カテゴリ row, which
 * overrides the 全員 row (permission.md).
 */
enum PermissionSubject: string
{
    case User = 'user';
    case Category = 'category';
    case Everyone = 'everyone';

    public function label(): string
    {
        return match ($this) {
            self::User => '個人',
            self::Category => '従業員カテゴリ',
            self::Everyone => '全員',
        };
    }

    /** Lower wins. */
    public function priority(): int
    {
        return match ($this) {
            self::User => 1,
            self::Category => 2,
            self::Everyone => 3,
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
