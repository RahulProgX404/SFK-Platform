<?php

/*
| Settings configuration — config('settings.*').
*/

return [
    'panel' => [
        'path' => env('SETTINGS_PANEL_PATH', 'settings'),
        // Serve this product on its own (sub)domain, e.g. settings.example.co.jp. Null = same host.
        'domain' => env('SETTINGS_PANEL_DOMAIN'),
        'brand' => env('SETTINGS_PANEL_BRAND', 'ERP 共通管理'),
    ],

    // Card shown on the dashboard selection portal (/portal).
    'portal' => [
        'label' => 'ERP 共通管理',
        'description' => 'ユーザー・組織・役職など、システム全体の設定を管理します。',
        'icon' => 'settings',
    ],
];
