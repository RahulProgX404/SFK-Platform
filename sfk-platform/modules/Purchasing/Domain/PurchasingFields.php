<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain;

use Modules\Purchasing\Domain\Payables\PayableCategories;

/**
 * Field labels of 05. 納品(仕入)入力, 07. 支払入力, 15. 運送請求データ取込, 20. 消耗品発注 and 23. 買掛管理 exactly as in
 * the legacy system (SYSTEM_BRAIN APP_05 / APP_07 / APP_15 / APP_20 / APP_23B fields.md), by attribute.
 */
final class PurchasingFields
{
    private const AUTHORS = [
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システムID',
    ];

    public const PURCHASE = [
        'purchased_on' => '納品(仕入)日',
        'supplier_id' => '仕入先',
        'tax_mode' => '消費税区分',
        'master_category' => '仕入カテゴリ(マスタ)',
        'category' => '仕入カテゴリ',
        'purchase_number' => '納品(仕入)入力No',
        'order_id' => '受注No',
        'order_info' => '注番基本情報',
        'product_name' => '品名',
        'quantity' => '数量',
        'unit_price' => '単価',
        'amount' => '金額',
        'cutting_amount' => '断裁金額',
        'is_fsc' => 'FSC',
        'plate_amount' => '版代・木型代',
        'stored_plate_amount' => '置き版代・セット代・その他',
        'spot_color_amount' => '特色・色替・CPU・その他',
        'period_end' => '帳端処理',
        'same_order' => '同注番の仕入履歴',
        'overruns' => '仕入原価超過',
        'notified_users' => '通知先',
        'total' => '仕入金額合計',
        'id' => '納品(仕入)ID',
        'supplier_rounding' => '端数区分(仕入)',
        'purchase_month' => '仕入年月',
        'supplier_code' => '仕入先/得意先ID',
        'effective_category' => '仕入カテゴリ判定',
        ...self::AUTHORS,
    ];

    public const PAYMENT = [
        'paid_on' => '支払日',
        'total' => '支払金額合計',
        'lines' => '支払内容詳細',
        'supplier_id' => '仕入先',
        'supplier_code' => '仕入先ID',
        'method' => '金種名',
        'amount' => '支払額',
        'withdrawn_on' => '出金日',
        'notes' => '備考',
        'payment_month' => '支払年月',
        'id' => '支払入力ID',
        ...self::AUTHORS,
    ];

    public const FREIGHT = [
        'charged_on' => '日付',
        'order_id' => '注番',
        'amount' => '金額',
        'carrier_number' => '運送会社番号',
        'purchase_id' => '納品(仕入)入力No',
        'unit_price' => '単価',
        'quantity' => '数量',
        'cutting_amount' => '断裁金額',
        'plate_amount' => '版代',
        'stored_plate_amount' => '置き版代',
        'spot_color_amount' => '特色',
        'notes' => '備考',
        'id' => '運送請求データID',
        ...self::AUTHORS,
    ];

    public const CONSUMABLE_ORDER = [
        'ordered_on' => '発注日',
        'supplier_id' => '発注先',
        'orderers' => '発注者',
        'lines' => '発注明細',
        'consumable_id' => '商品名',
        'unit_price' => '単価',
        'quantity' => '数量',
        'unit' => '単位',
        'requested_on' => '希望納期',
        'orderer' => '発注者名又は番号',
        'delivered_on' => '納品日',
        'line_notes' => '備考',
        'notes' => '備考',
        'status' => 'ステータス',
        'assignee' => '作業者',
        'id' => 'レコード番号',
        'supplier_record' => '発注先レコード番号',
        'supplier_code' => '発注先得意先コード',
        'purchase_category' => '仕入カテゴリ',
        'tax_mode' => '消費税区分',
        'rounding' => '端数区分',
        'calculation_unit' => '計算区分',
        'group' => 'グループ区分',
        'tel' => 'TEL',
        'fax' => 'FAX',
        ...self::AUTHORS,
    ];

    public const PAYABLE = [
        'year_month' => '年月',
        'supplier_code' => '得意先ID',
        'supplier_id' => '得意先名',
        'purchase_category' => '仕入カテゴリ',
        'tax_mode' => '消費税区分',
        'rounding' => '端数区分',
        'calculation_unit' => '計算区分',
        'previous_balance' => '前月買掛残',
        'paid' => '当月支払額',
        'net_balance' => '差引残高',
        'cutting_amount' => '断裁金額',
        'plate_amount' => '版代・木型代',
        'stored_plate_amount' => '置き版代',
        'spot_color_amount' => '特色・色替・その他',
        'purchase_amount' => '仕入額',
        'tax_amount' => '消費税額',
        'tax_adjustment' => '消費税端数調整',
        'month_total' => '当月合計金額',
        'closing_balance' => '当月買掛残',
        'breakdown' => '▼ 仕入カテゴリ別内訳',
        'cat_materials_goods' => '材料+商品仕入',
        ...PayableCategories::LABELS,
        'other_months' => '過去買掛レコード',
        'month_purchases' => '仕入情報詳細',
        'month_payments' => '支払明細',
        'month_start' => '年月日',
        'id' => 'レコード番号',
        ...self::AUTHORS,
    ];
}
