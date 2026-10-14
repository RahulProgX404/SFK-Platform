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

    // Card shown on the dashboard selection portal (/portal).
    'portal' => [
        'label' => '白石封筒',
        'description' => '受注・生産・購買・売上・請求など、日々の業務を行います。',
        'icon' => 'erp',
    ],
];
