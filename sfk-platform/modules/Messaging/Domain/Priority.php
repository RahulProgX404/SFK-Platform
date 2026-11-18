<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/** The priority of a message or an announcement. */
enum Priority: int
{
    case Normal = 0;
    case Important = 1;
    case Urgent = 2;

    public function label(): string
    {
        return match ($this) {
            self::Normal => '通常',
            self::Important => '重要',
            self::Urgent => '至急',
        };
    }

    /** Announcements store the name. */
    public function key(): string
    {
        return match ($this) {
            self::Normal => 'normal',
            self::Important => 'important',
            self::Urgent => 'urgent',
        };
    }

    public static function fromKey(?string $key): self
    {
        return match ($key) {
            'important' => self::Important,
            'urgent' => self::Urgent,
            default => self::Normal,
        };
    }
}
