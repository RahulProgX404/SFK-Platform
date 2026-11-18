<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Application\Realtime\Realtime;
use Modules\Messaging\Domain\Availability;

/**
 * Real time without a WebSocket server (docs/messaging.md "Real time"), on the cache (Valkey / Redis; the database cache
 * on Render): writes change small versions, the open pages ask for them (MessengerPulse — a cache read, no session,
 * no database query on Valkey) and fetch what changed only when a version moved.
 *
 * - inbox:<user>       changes when a conversation of the user changed (new message, edit, member, …) → the inbox
 *                      and the unread badge are read again;
 * - conversation:<id>  changes with every message / edit / reaction / pin of the conversation → the open chat
 *                      fetches the messages after the newest it has, and the changed ones;
 * - read:<id>          changes when a member read or received → the read marks are read again;
 * - typing:<id>        who is typing (each entry expires after a few seconds; nothing is stored per keystroke);
 * - seen:<user>        when the user's pages last asked (オンライン / 最終アクセス), unless they hide it (hidden:<user>).
 *
 * Everything is written after the transaction commits (a rolled-back message is never announced). Values are plain
 * arrays and strings (the cache unserializes no objects).
 *
 * With WebSockets (Core Realtime, Laravel Reverb) every change is also pushed: `changed`, `read` and `typing` on the
 * conversation's channel `sfk.conversation.<id>` (members only, MessagingServiceProvider), `inbox` on each member's
 * own `sfk.user.<id>` — the pages then poll only as a safety net. Pushes carry versions and ids, never a message: the
 * page fetches what it may see through the API.
 */
final class Signals
{
    private const PREFIX = 'messaging:';

    private const TTL_DAYS = 14;

    /**
     * A conversation changed for these members (their inboxes) and its open chats.
     *
     * @param  list<int>  $userIds
     */
    public static function conversationChanged(int $conversationId, array $userIds): void
    {
        DB::afterCommit(function () use ($conversationId, $userIds): void {
            $version = self::version();
            $values = [self::PREFIX.'conversation:'.$conversationId => $version];
            foreach (array_unique($userIds) as $userId) {
                $values[self::PREFIX.'inbox:'.(int) $userId] = $version;
            }
            Cache::putMany($values, now()->addDays(self::TTL_DAYS));
            Realtime::send([self::conversationChannel($conversationId)], 'changed', ['v' => $version]);
            Realtime::send(array_map(fn (int $id): string => Realtime::userChannel($id), array_values(array_unique(array_map('intval', $userIds)))), 'inbox', ['v' => $version]);
        });
    }

    /**
     * Only these users' inboxes changed (their own pin / archive / read state, which the other members do not see).
     *
     * @param  list<int>  $userIds
     */
    public static function inboxesChanged(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        DB::afterCommit(function () use ($userIds): void {
            $version = self::version();
            $values = [];
            foreach (array_unique($userIds) as $userId) {
                $values[self::PREFIX.'inbox:'.(int) $userId] = $version;
            }
            Cache::putMany($values, now()->addDays(self::TTL_DAYS));
            Realtime::send(array_map(fn (int $id): string => Realtime::userChannel($id), array_values(array_unique(array_map('intval', $userIds)))), 'inbox', ['v' => $version]);
        });
    }

    /** A member read or received messages: the read marks of the open chats change. */
    public static function readChanged(int $conversationId): void
    {
        DB::afterCommit(function () use ($conversationId): void {
            $version = self::version();
            Cache::put(self::PREFIX.'read:'.$conversationId, $version, now()->addDays(self::TTL_DAYS));
            Realtime::send([self::conversationChannel($conversationId)], 'read', ['v' => $version]);
        });
    }

    /** The user is typing in the conversation (or stopped). */
    public static function typing(int $conversationId, int $userId, string $name, bool $typing): void
    {
        $key = self::PREFIX.'typing:'.$conversationId;
        $now = now()->getTimestamp();
        $entries = self::liveTyping(Cache::get($key), $now);
        if ($typing) {
            $entries[$userId] = [$name, $now + (int) config('messaging.pulse.typing_seconds', 6)];
        } else {
            unset($entries[$userId]);
        }

        $entries === [] ? Cache::forget($key) : Cache::put($key, $entries, 30);
        Realtime::send([self::conversationChannel($conversationId)], 'typing', ['user' => $userId, 'typing' => $typing]);
    }

    /** The private channel of a conversation's open chats (members only). */
    public static function conversationChannel(int $conversationId): string
    {
        return 'sfk.conversation.'.$conversationId;
    }

    /** The user's pages are open (throttled by the caller), and whether the user has been inactive there. */
    public static function seen(int $userId, bool $idle = false): void
    {
        Cache::putMany([
            self::PREFIX.'seen:'.$userId => now()->getTimestamp(),
            self::PREFIX.'idle:'.$userId => $idle,
        ], now()->addDays(self::TTL_DAYS));
    }

    /** Whether the user hides オンライン / 最終アクセス from the others (メッセージ設定). */
    public static function hidePresence(int $userId, bool $hide): void
    {
        $hide ? Cache::forever(self::PREFIX.'hidden:'.$userId, true) : Cache::forget(self::PREFIX.'hidden:'.$userId);
        if ($hide) {
            Cache::forget(self::PREFIX.'seen:'.$userId);
        }
    }

    /** The user's 在席状況 (auto / away / dnd), for the others' presence. */
    public static function setStatus(int $userId, Availability $status): void
    {
        $status === Availability::Auto ? Cache::forget(self::PREFIX.'status:'.$userId) : Cache::forever(self::PREFIX.'status:'.$userId, $status->value);
    }

    /**
     * What a page of the user asks (one read of several keys): versions, who is typing in the open conversation and
     * the presence of the direct conversation's other person. $idle: the page saw no activity for a while.
     *
     * @return array{i: string, c: string, r: string, t: list<string>, o: array{state: string, online: bool, seen: int|null}|null}
     */
    public static function pulse(int $userId, int $conversationId, int $peerId, bool $idle = false): array
    {
        $keys = [
            'inbox' => self::PREFIX.'inbox:'.$userId,
            'mine' => self::PREFIX.'seen:'.$userId,
            'mineIdle' => self::PREFIX.'idle:'.$userId,
            'hidden' => self::PREFIX.'hidden:'.$userId,
            'policy' => self::PREFIX.'presence-policy',
        ];
        if ($conversationId > 0) {
            $keys += [
                'conversation' => self::PREFIX.'conversation:'.$conversationId,
                'read' => self::PREFIX.'read:'.$conversationId,
                'typing' => self::PREFIX.'typing:'.$conversationId,
            ];
        }
        if ($peerId > 0) {
            $keys += self::presenceKeys($peerId, 'peer');
        }

        $values = Cache::many(array_values($keys));
        $value = fn (string $name): mixed => isset($keys[$name]) ? ($values[$keys[$name]] ?? null) : null;
        self::touch($userId, $value('mine'), (bool) $value('mineIdle'), (bool) $value('hidden'), $idle);

        $typing = [];
        foreach (self::liveTyping($value('typing'), now()->getTimestamp()) as $id => [$name]) {
            if ((int) $id !== $userId) {
                $typing[] = $name;
            }
        }

        return [
            'i' => (string) $value('inbox'),
            'c' => (string) $value('conversation'),
            'r' => (string) $value('read'),
            't' => $typing,
            'o' => $peerId > 0 ? self::presenceOf($value('peer'), (bool) $value('peerHidden'), $value('peerStatus'), (bool) $value('peerIdle'), $value('policy')) : null,
        ];
    }

    /**
     * The global pulse of every page (Core NotificationPulse channel 'm'): the inbox version, and the user counts as
     * present (or 離席中 when the page saw no activity for a while). One read of a few keys.
     */
    public static function inboxPulse(int $userId, bool $idle = false): string
    {
        $keys = [self::PREFIX.'inbox:'.$userId, self::PREFIX.'seen:'.$userId, self::PREFIX.'idle:'.$userId, self::PREFIX.'hidden:'.$userId];
        $values = Cache::many($keys);
        self::touch($userId, $values[$keys[1]] ?? null, (bool) ($values[$keys[2]] ?? false), (bool) ($values[$keys[3]] ?? false), $idle);

        return (string) ($values[$keys[0]] ?? '');
    }

    /**
     * Presence of several users at once (the inbox's dots, member lists).
     *
     * @param  list<int>  $userIds
     * @return array<int, array{state: string, online: bool, seen: int|null}>
     */
    public static function presence(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $keys = ['policy' => self::PREFIX.'presence-policy'];
        foreach ($userIds as $id) {
            $keys += self::presenceKeys($id, (string) $id);
        }
        $values = Cache::many(array_values($keys));
        $value = fn (string $name): mixed => $values[$keys[$name]] ?? null;

        $presence = [];
        foreach ($userIds as $id) {
            $presence[$id] = self::presenceOf($value($id.''), (bool) $value($id.'Hidden'), $value($id.'Status'), (bool) $value($id.'Idle'), $value('policy'));
        }

        return $presence;
    }

    /** @return array<string, string> */
    private static function presenceKeys(int $userId, string $prefix): array
    {
        return [
            $prefix => self::PREFIX.'seen:'.$userId,
            $prefix.'Hidden' => self::PREFIX.'hidden:'.$userId,
            $prefix.'Status' => self::PREFIX.'status:'.$userId,
            $prefix.'Idle' => self::PREFIX.'idle:'.$userId,
        ];
    }

    /**
     * online / away / dnd / offline (Availability), with the last time seen; nothing when the user hides it or the company
     * switched presence off (policy false).
     *
     * @return array{state: string, online: bool, seen: int|null}
     */
    private static function presenceOf(mixed $seen, bool $hidden, mixed $status, bool $idle, mixed $policy): array
    {
        if ($policy === false || $hidden || ! is_numeric($seen)) {
            return ['state' => 'offline', 'online' => false, 'seen' => null];
        }

        $state = (Availability::tryFrom((string) $status) ?? Availability::Auto)
            ->presence((int) $seen, $idle, now()->getTimestamp(), (int) config('messaging.pulse.online_seconds', 60));

        return ['state' => $state, 'online' => $state !== 'offline', 'seen' => (int) $seen];
    }

    /**
     * Marks the user present at most every 20 seconds (a write per pulse would be a write every few seconds a page), and
     * at once when they go idle or come back.
     */
    private static function touch(int $userId, mixed $seen, bool $wasIdle, bool $hidden, bool $idle): void
    {
        if (! $hidden && (! is_numeric($seen) || $wasIdle !== $idle || now()->getTimestamp() - (int) $seen >= 20)) {
            self::seen($userId, $idle);
        }
    }

    /** @return array<int|string, array{0: string, 1: int}> */
    private static function liveTyping(mixed $entries, int $now): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return array_filter($entries, fn (mixed $entry): bool => is_array($entry) && isset($entry[0], $entry[1]) && (int) $entry[1] >= $now);
    }

    private static function version(): string
    {
        return strtolower((string) Str::ulid());
    }
}
