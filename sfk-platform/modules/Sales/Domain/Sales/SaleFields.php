<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Sales;

/**
 * Field labels of 06. 売上入力 exactly as in the legacy system (SYSTEM_BRAIN APP_06 fields.md), by attribute (record
 * columns and the values shown from the customer / job).
 */
final class SaleFields
{
    public const LABELS = [
        'kind' => '区分',
        'sold_on' => '売上日',
        'sales_number' => '売上No',
        'customer_id' => '売上先',
        'closing_day' => '請求締日',
        'closing_day_override' => '請求締日変更',
        'billing_date' => '請求日付',
        'delivery_due_on' => '納品予定日',
        'delivery_note_recipient' => '納品書送付先',
        'invoice_recipient' => '請求書送付先',
        'other_address' => 'その他の送付先を入力',
        'manual_postal_code' => '郵便番号(手動)',
        'manual_address' => '納品先住所(手動)',
        'parent_postal_code' => '郵便番号(親会社)',
        'parent_address' => '納品先住所(親会社)',
        'customer_postal_code' => '郵便番号(得意先)',
        'customer_address' => '納品先住所(得意先)',
        'quantity' => '売上数量',
        'unit' => '単位',
        'unit_price' => '売上単価',
        'amount' => '売上金額',
        'estimated_cost' => '見積原価合計',
        'estimated_expense' => '見積経費',
        'tax_rate' => '税率',
        'amount_with_tax' => '売上金額(税込)',
        'tax' => '消費税',
        'summary' => '摘要',
        'order_id' => '注番',
        'order_customer' => '得意先名',
        'product_name' => '品名',
        'assignees' => '担当者',
        'order_quantity' => '受注数量',
        'order_sold_quantity' => '現在売上済数量',
        'order_remaining' => '売上残数量',
        'order_sales_amount' => '注番売上金額',
        'is_fsc' => 'FSC',
        'order_sold_amount' => '注番売上済金額',
        'allocations' => '按分',
        'allocation_user' => '担当者(按分)',
        'allocation_ratio' => '割合',
        'allocation_amount' => '売上金額(按分)',
        'same_order_sales' => '同注番の売上履歴',
        'needs' => '必要なもの',
        'handover' => '受け渡し',
        'id' => '売上入力ID',
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'delivered' => '納品書作成',
        'delivery_note_id' => '納品書番号',
        'invoiced' => '請求書作成',
        'invoice_id' => '請求書番号',
        'locked' => '編集',
        'postal_code_p' => '郵便番号(P)',
        'address_p' => '住所(P)',
        'customer_code' => '売上先の得意先コード',
        'customer_rounding' => '端数区分',
        'effective_closing_day' => '締日判定',
        'billing_month' => '月度',
        'sales_month' => '売上年月',
        'legacy_id' => '旧システム売上入力ID',
    ];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }
}
