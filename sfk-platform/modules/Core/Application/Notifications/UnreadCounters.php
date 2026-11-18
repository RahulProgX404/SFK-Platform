<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use App\Models\User;
use Closure;

/**
 * What is unread for a user, outside the bell — shown next to the unread 通知 on the portal (core::portal, 今日 card) and
 * kept in step there while the page is open (GET /portal/unread, asked when the user's pulse changes). A module adds its
 * own count, e.g. 社内メッセージ its unread messages. Counts are read from the database: only on the portal's first
 * load and when the pulse said something changed — never every few seconds.
 */
final class UnreadCounters
{
    /** @var array<string, array{label: string, count: Closure(User): ?int, url: Closure(User): ?string}> */
    private static array $counters = [];

    /**
     * @param  Closure(User $user): ?int  $count  null = the user does not use it (not shown)
     * @param  Closure(User $user): ?string|null  $url  where a click on the count goes
     */
    public static function register(string $key, string $label, Closure $count, ?Closure $url = null): void
    {
        self::$counters[$key] = ['label' => $label, 'count' => $count, 'url' => $url ?? fn (): ?string => null];
    }

    public static function forget(string $key): void
    {
        unset(self::$counters[$key]);
    }

    /**
     * The unread 通知 of the bell first, then the modules' counts.
     *
     * @return list<array{key: string, label: string, count: int, url: string|null}>
     */
    public static function for(User $user): array
    {
        $items = [['key' => 'notifications', 'label' => '通知', 'count' => $user->unreadNotifications()->count(), 'url' => null]];
        foreach (self::$counters as $key => $counter) {
            // the portal is the way in: a module's count that fails (e.g. its migration not run yet) is reported and left out
            $count = rescue(fn (): ?int => ($counter['count'])($user), null);
            if ($count !== null) {
                $items[] = ['key' => $key, 'label' => $counter['label'], 'count' => max(0, $count), 'url' => ($counter['url'])($user)];
            }
        }

        return $items;
    }
}
