<?php

/*
| ERP shell configuration — config('erp.*').
*/

return [
    'panel' => [
        'path' => env('ERP_PANEL_PATH', 'erp'),
        // Serve the ERP on its own (sub)domain, e.g. erp.example.co.jp. Null = same host.
        'domain' => env('ERP_PANEL_DOMAIN'),
        'brand' => env('ERP_PANEL_BRAND', 'SFK システム'),
    ],

    // The dashboard (/erp): 新規作成 of its header — app code => label, shown where the user may add records.
    'dashboard' => [
        'create' => [
            'order-entry' => '受注入力',
            'sales-entry' => '売上入力',
            'daily-report' => '日報入力',
        ],
    ],

    // Card shown on the dashboard selection portal (/portal).
    'portal' => [
        'label' => '白石封筒',
        'icon' => 'erp',
        'subtitle' => '業務システム',
    ],
];
