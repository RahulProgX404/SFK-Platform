<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Closure;

/**
 * More in the pulse every page asks (NotificationPulse, GET /core/pulse/…): a module registers a channel that answers
 * from the cache alone — never the database, the pulse is asked every few seconds by every open page — and its value
 * travels as `x.<key>`; the page script announces each answer as the window event `sfk:pulse`. E.g. 社内メッセージ adds
 * its inbox version ('m'), so one request a page serves both the bell and the messenger's unread badge.
 */
final class PulseChannels
{
    /** @var array<string, Closure(int): mixed> */
    private static array $channels = [];

    /** @param  Closure(int $userId): mixed  $read */
    public static function register(string $key, Closure $read): void
    {
        self::$channels[$key] = $read;
    }

    /** @return array<string, mixed> */
    public static function read(int $userId): array
    {
        $values = [];
        foreach (self::$channels as $key => $read) {
            $values[$key] = $read($userId);
        }

        return $values;
    }
}
