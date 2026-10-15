<?php

/*
| Settings configuration — config('settings.*').
*/

return [
    'panel' => [
        'path' => env('SETTINGS_PANEL_PATH', 'settings'),
        // Serve this product on its own (sub)domain, e.g. settings.example.co.jp. Null = same host.
        'domain' => env('SETTINGS_PANEL_DOMAIN'),
        'brand' => env('SETTINGS_PANEL_BRAND', '共通管理'),
        // Only ユーザー種別 super_admin / admin may open this panel; it is not a 従業員カテゴリ dashboard.
        'admin_only' => true,
    ],

    // Card shown on the dashboard selection portal (/portal).
    'portal' => [
        'label' => '共通管理',
        'icon' => 'settings',
    ],
];
