<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Messaging\Http\MessengerAssets;

/**
 * GET /messaging/assets/messenger.css|js?v=<version>: the messenger's stylesheet and script, only on the messenger page
 * (never in every page's theme), kept by the browser for a year (the version follows the content).
 */
final class MessengerAssetController
{
    public function css(): Response
    {
        return self::respond(MessengerAssets::css(), 'text/css; charset=UTF-8');
    }

    public function js(): Response
    {
        return self::respond(MessengerAssets::js(), 'application/javascript; charset=UTF-8');
    }

    private static function respond(string $content, string $type): Response
    {
        return response($content, 200, [
            'Content-Type' => $type,
            'Cache-Control' => request()->query('v') === MessengerAssets::version() ? 'public, max-age=31536000, immutable' : 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
