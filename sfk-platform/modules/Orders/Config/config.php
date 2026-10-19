<?php

/*
| Orders module configuration — config('orders.*').
*/

return [

    /*
    |--------------------------------------------------------------------------
    | 受注書の読み込み (FAX order import on the 04. 受注入力 create screen)
    |--------------------------------------------------------------------------
    | The legacy customization read a customer's faxed purchase order (image / PDF) with the Claude API in the
    | browser and filled the new order with it. Here the file is read on the server; the API key never reaches the
    | browser. Only orders issued by `issuer` are accepted. `stamps` are the surnames of the issuer's order staff
    | stamped in 検印欄 (they start 品名 and choose 製袋予定規格); `priority_stamp` written in the order-staff / date
    | fields forces `priority_schedule`. `manufacturer` is the partner set as 仕入先(製造場所).
    */
    'fax_import' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ORDERS_FAX_IMPORT_MODEL', 'claude-opus-5-5'),
        'effort' => env('ORDERS_FAX_IMPORT_EFFORT', 'low'),
        'max_tokens' => 16000,
        'issuer' => 'ハート封筒株式会社',
        'reader_company' => '白石封筒',
        'stamps' => [
            '古井' => 'カマス貼 1F・2F',
            '新田' => '2・5・6号機 190㎜巾まで',
            '梅村' => '3号機 T240(245㎜巾まで)',
            'ハ田' => 'カマス貼 1F・2F',
            '金山' => '3号機 T240(245㎜巾まで)',
        ],
        'priority_stamp' => '金山',
        'priority_schedule' => '3号機 T240(245㎜巾まで)',
        'manufacturer' => '社内',
        'category1' => '封筒(別注)',
        'category2' => '紙支給',
        // largest file accepted, in kilobytes
        'max_file_size' => 20480,
    ],

];
