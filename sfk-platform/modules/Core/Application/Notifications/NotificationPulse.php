<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Application\Realtime\Realtime;

/**
 * Real-time 通知 without a WebSocket server (user, 2026-11-16 — the bell only showed new 通知 after a reload): each user
 * has a pulse in the cache (Valkey / Redis) that changes whenever they receive a 通知 — its version and the newest
 * 通知's title, body and link. Every open page asks for it every few seconds (core::filament.notification-pulse-script,
 * GET /core/pulse/{user}/{key}) — a cache read without the session or the database, so asking often costs nothing of
 * the ~80 ms database round trip — and, when it changed, refreshes the bell and shows the 通知 as a toast.
 *
 * The key in the address is a signature of the user id with the app key: only the user's own pages know it.
 * With WebSockets (Realtime, Laravel Reverb) the same pulse is also pushed the moment it changes; the pages then ask
 * only every minute, as a safety net (docs/realtime.md).
 */
final class NotificationPulse
{
    /** How long a pulse is kept: a page whose pulse is gone only sees the next 通知. */
    private const TTL_DAYS = 14;

    /**
     * Marks a new 通知 for the user (called after the transaction that wrote it committed).
     *
     * @param  array<string, mixed>  $data  the stored 通知 (Filament database message: title, body, icon, actions)
     */
    public static function bump(int $userId, array $data): void
    {
        $action = is_array($data['actions'] ?? null) ? ($data['actions'][0] ?? null) : null;

        $pulse = [
            'v' => (string) Str::ulid(),
            't' => Str::limit(trim(strip_tags((string) ($data['title'] ?? ''))), 120),
            'b' => Str::limit(trim(strip_tags((string) ($data['body'] ?? ''))), 200),
            'i' => is_string($data['icon'] ?? null) ? $data['icon'] : null,
            'c' => is_string($data['iconColor'] ?? null) ? $data['iconColor'] : null,
            'u' => is_array($action) && is_string($action['url'] ?? null) ? $action['url'] : null,
            'l' => is_array($action) && is_string($action['label'] ?? null) ? $action['label'] : null,
        ];
        Cache::put(self::key($userId), $pulse, now()->addDays(self::TTL_DAYS));
        // pushed at once to the user's open pages (WebSockets, Realtime); the polled pulse stays the fallback
        Realtime::send([Realtime::userChannel($userId)], 'notification', $pulse);
    }

    /** @return array{v: string, t: string, b: string, i: string|null, c: string|null, u: string|null, l?: string|null}|null the newest pulse */
    public static function current(int $userId): ?array
    {
        $pulse = Cache::get(self::key($userId));

        return is_array($pulse) && is_string($pulse['v'] ?? null) ? $pulse : null;
    }

    /** The version of the user's pulse ('' while there is none). */
    public static function version(int $userId): string
    {
        return self::current($userId)['v'] ?? '';
    }

    /** The signature of the user's pulse address. */
    public static function signature(int $userId): string
    {
        return substr(hash_hmac('sha256', 'notification-pulse|'.$userId, (string) config('app.key')), 0, 40);
    }

    public static function verifies(int $userId, string $signature): bool
    {
        return hash_equals(self::signature($userId), $signature);
    }

    /** The address the user's pages ask. */
    public static function url(int $userId): string
    {
        return route('core.pulse', ['user' => $userId, 'key' => self::signature($userId)]);
    }

    private static function key(int $userId): string
    {
        return 'core:notification-pulse:'.$userId;
    }
}
