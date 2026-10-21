<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\Quotations;

/**
 * Field labels of 09. 見積書管理 exactly as in the legacy system (SYSTEM_BRAIN APP_09 fields.md), by attribute.
 */
final class QuotationFields
{
    public const LABELS = [
        'quoted_on' => '見積日付',
        'customer_id' => '得意先名',
        'assignees' => '担当者',
        'quote_number' => '見積書番号',
        'product_name' => '品名',
        'size' => 'サイズ',
        'paper' => '紙',
        'printing' => '印刷',
        'window' => '窓',
        'flap_glue' => '口糊',
        'free_text_1' => 'その他フリー入力(1)',
        'free_text_2' => 'その他フリー入力(2)',
        'free_text_3' => 'その他フリー入力(3)',
        'total' => '見積金額(税抜)',
        'lines' => '見積内容',
        'sheets' => '枚数',
        'unit_price' => '単価',
        'amount' => '金額',
        'notes' => '備考',
        'pdf_files' => '見積書PDF',
        'customer_code' => '得意先ID',
        'file_name' => 'ファイル保存名',
        'id' => '見積書管理ID',
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システムID',
    ];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }
}
