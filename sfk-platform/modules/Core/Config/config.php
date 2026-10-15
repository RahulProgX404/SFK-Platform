<?php

/*
| Core module configuration — available as config('core.*').
*/

return [

    // Name of the whole system, shown on the single login page (/login) and the account pages.
    'system_name' => env('SYSTEM_NAME', 'SFK システム'),

    /*
    |--------------------------------------------------------------------------
    | Document number sequences
    |--------------------------------------------------------------------------
    | Formats carried over from the legacy system (see SYSTEM_BRAIN 12_BUSINESS_RULES):
    |   order          sfk-0000001   BR-OR-01 (04 受注入力 注番)
    |   purchase       0000001       BR-PU-01 (05 納品(仕入)入力No)
    |   delivery_note  0000001       BR-SA-14 (10 納品書番号)
    |   invoice        0000001       BR-SA-14 (11 請求書番号)
    |   partner        000001        01 取引先コード (numbered on save, BR-MD-01)
    | Numbering of 売上No / 見積書番号 is UNKNOWN (U-15) — add once confirmed.
    | During migration call DocumentNumberGenerator::ensureNextAtLeast() with max(existing)+1.
    */
    'sequences' => [
        'order' => ['prefix' => 'sfk-', 'padding' => 7, 'start' => 1],
        'purchase' => ['prefix' => '', 'padding' => 7, 'start' => 1],
        'delivery_note' => ['prefix' => '', 'padding' => 7, 'start' => 1],
        'invoice' => ['prefix' => '', 'padding' => 7, 'start' => 1],
        'partner' => ['prefix' => '', 'padding' => 6, 'start' => 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super administrator
    |--------------------------------------------------------------------------
    | The super-admin account is owned by the environment, not by the user management screens.
    | `php artisan core:super-admin` (also run by `db:seed`) creates/updates it from these values;
    | it cannot be edited or deleted in the settings panel. ADMIN_* are the older variable names.
    */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', env('ADMIN_NAME', 'システム管理者')),
        'email' => env('SUPER_ADMIN_EMAIL', env('ADMIN_EMAIL')),
        'password' => env('SUPER_ADMIN_PASSWORD', env('ADMIN_PASSWORD')),
    ],

];
