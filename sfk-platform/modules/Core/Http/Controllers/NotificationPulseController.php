<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Application\Notifications\NotificationPulse;
use Modules\Core\Application\Notifications\PulseChannels;

/**
 * GET /core/pulse/{user}/{key}: the user's 通知 pulse (NotificationPulse), asked by their open pages every few seconds.
 * Outside the web middleware on purpose — no session, no database, only cache reads (the 通知 pulse and the channels
 * other modules add, PulseChannels).
 */
final class NotificationPulseController
{
    public function __invoke(string $user, string $key): JsonResponse
    {
        if (! ctype_digit($user) || ! NotificationPulse::verifies((int) $user, $key)) {
            abort(404);
        }

        $channels = PulseChannels::read((int) $user);

        return response()
            ->json([...(NotificationPulse::current((int) $user) ?? ['v' => '']), ...($channels !== [] ? ['x' => $channels] : [])])
            ->header('Cache-Control', 'no-store, private');
    }
}
