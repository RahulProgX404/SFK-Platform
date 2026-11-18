<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

/**
 * 在席状況. The user sets auto (オンライン while a page is open and used, 離席中 after a while without activity), away or
 * dnd (取り込み中: no sound, toasts or browser notifications); others see the resulting presence — online, away, dnd or
 * offline (no page asked for a while, or presence hidden by the user or the company). An open browser tab alone is not
 * "online": the pages report activity, and a silent connection expires.
 */
enum Availability: string
{
    case Auto = 'auto';
    case Away = 'away';
    case Dnd = 'dnd';

    public function label(): string
    {
        return match ($this) {
            self::Auto => '自動',
            self::Away => '離席中',
            self::Dnd => '取り込み中',
        };
    }

    /**
     * What others see.
     *
     * @param  int|null  $seen  when a page last asked (unix time)
     * @param  bool  $idle  that page reported no activity for a while
     */
    public function presence(?int $seen, bool $idle, int $now, int $onlineSeconds): string
    {
        if ($seen === null || $now - $seen > $onlineSeconds) {
            return 'offline';
        }

        return match ($this) {
            self::Dnd => 'dnd',
            self::Away => 'away',
            self::Auto => $idle ? 'away' : 'online',
        };
    }
}
