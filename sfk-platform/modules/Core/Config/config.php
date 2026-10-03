<?php

/*
| Core module configuration — available as config('core.*').
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Document number sequences
    |--------------------------------------------------------------------------
    | Formats carried over from the kintone system (see SYSTEM_BRAIN 12_BUSINESS_RULES):
    |   order          sfk-0000001   BR-OR-01 (04 受注入力 注番)
    |   purchase       0000001       BR-PU-01 (05 納品(仕入)入力No)
    |   delivery_note  0000001       BR-SA-14 (10 納品書番号)
    |   invoice        0000001       BR-SA-14 (11 請求書番号)
    | Numbering of 売上No / 見積書番号 is UNKNOWN (U-15) — add once confirmed.
    | During migration call DocumentNumberGenerator::ensureNextAtLeast() with max(existing)+1.
    */
    'sequences' => [
        'order' => ['prefix' => 'sfk-', 'padding' => 7, 'start' => 1],
        'purchase' => ['prefix' => '', 'padding' => 7, 'start' => 1],
        'delivery_note' => ['prefix' => '', 'padding' => 7, 'start' => 1],
        'invoice' => ['prefix' => '', 'padding' => 7, 'start' => 1],
    ],

];
