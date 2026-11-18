<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/**
 * Who may do something in a group (グループの設定): every member or only the group's 管理者.
 */
enum GroupRule: string
{
    case Everyone = 'everyone';
    case Admins = 'admins';

    public function allows(ParticipantRole $role): bool
    {
        return $this === self::Everyone || $role === ParticipantRole::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Everyone => '全メンバー',
            self::Admins => '管理者のみ',
        };
    }
}
