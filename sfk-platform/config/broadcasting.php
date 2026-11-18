<?php

/*
| Real time (docs/realtime.md): by default ("null", the production setting — no WebSocket server, no Laravel Cloud
| add-on) the pages poll the cache pulses. Optionally, with BROADCAST_CONNECTION=reverb, 通知 and 社内メッセージ are
| pushed over WebSockets by Laravel Reverb: the app publishes to Reverb's HTTP API at REVERB_HOST:REVERB_PORT (inside
| the server or Docker network); browsers connect to the public address (REVERB_PUBLIC_* — by default the page's own
| origin, where nginx forwards /app to Reverb).
*/

return [

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            // a publish must never hold up a request: Reverb down = the pages keep polling
            'client_options' => [
                'timeout' => 2,
                'connect_timeout' => 1,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    // where browsers connect (Modules\Core\Application\Realtime\Realtime::clientConfig()); empty host = the page's origin
    'public' => [
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => env('REVERB_PUBLIC_PORT'),
        'scheme' => env('REVERB_PUBLIC_SCHEME'),
    ],

];
