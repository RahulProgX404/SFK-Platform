<?php

declare(strict_types=1);

namespace Modules\Messaging\Http;

/**
 * The address an open messenger asks every ~1.5 s (Signals::pulse) and posts 入力中 to: signed for the user, the open
 * conversation and its other person (direct), so it needs neither the session nor the database — the signature,
 * handed out only after MessagingAccess let the user open the conversation, is the permission. It reveals versions,
 * the ids of who is typing and the other person's presence — never a message.
 */
final class MessengerPulse
{
    public static function signature(int $userId, int $conversationId, int $peerId): string
    {
        return substr(hash_hmac('sha256', 'messaging-pulse|'.$userId.'|'.$conversationId.'|'.$peerId, (string) config('app.key')), 0, 40);
    }

    public static function verifies(int $userId, int $conversationId, int $peerId, string $signature): bool
    {
        return hash_equals(self::signature($userId, $conversationId, $peerId), $signature);
    }

    public static function url(int $userId, int $conversationId = 0, int $peerId = 0): string
    {
        return route('messaging.pulse', [
            'user' => $userId,
            'conversation' => $conversationId,
            'peer' => $peerId,
            'key' => self::signature($userId, $conversationId, $peerId),
        ]);
    }
}
