<?php

declare(strict_types=1);

namespace Modules\Settings\Filament;

/** Sidebar groups of the settings panel, in display order. */
enum NavigationGroup: string
{
    case Users = 'ユーザー管理';
    case Apps = 'アプリ管理';

    /** @return list<string> */
    public static function labels(): array
    {
        return array_map(fn (self $group): string => $group->value, self::cases());
    }
}
