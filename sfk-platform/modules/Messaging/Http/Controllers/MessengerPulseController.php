<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Messaging\Application\Signals;
use Modules\Messaging\Http\MessengerPulse;

/**
 * GET  /messaging/pulse/{user}/{conversation}/{peer}/{key}: what changed (Signals::pulse) — asked every ~1.5 s by an
 *      open messenger; POST: 入力中 / stopped typing. Outside the web middleware on purpose (no session, no database, a
 *      cache read or write); the signed address is the permission (MessengerPulse).
 */
final class MessengerPulseController
{
    public function show(Request $request, string $user, string $conversation, string $peer, string $key): JsonResponse
    {
        [$userId, $conversationId, $peerId] = self::verified($user, $conversation, $peer, $key);

        return response()
            ->json(Signals::pulse($userId, $conversationId, $peerId, $request->boolean('idle')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function typing(Request $request, string $user, string $conversation, string $peer, string $key): JsonResponse
    {
        [$userId, $conversationId] = self::verified($user, $conversation, $peer, $key);
        if ($conversationId > 0) {
            Signals::typing($conversationId, $userId, (string) $userId, $request->boolean('typing'));
        }

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store, private');
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function verified(string $user, string $conversation, string $peer, string $key): array
    {
        if (! ctype_digit($user) || ! ctype_digit($conversation) || ! ctype_digit($peer)
            || ! MessengerPulse::verifies((int) $user, (int) $conversation, (int) $peer, $key)) {
            abort(404);
        }

        return [(int) $user, (int) $conversation, (int) $peer];
    }
}
