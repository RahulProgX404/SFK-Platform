<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

enum ParticipantRole: string
{
    /** 管理者 of a group: changes its settings and members */
    case Admin = 'admin';

    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Member => 'メンバー',
        };
    }
}
