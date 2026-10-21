<?php

declare(strict_types=1);

namespace Modules\Analytics\Domain;

/**
 * Field labels of 22. 過去の売上データ and 25. 販売先別売上計画 exactly as in the legacy system (SYSTEM_BRAIN APP_22 / APP_25
 * fields.md), by attribute. The month columns are labelled by FiscalYear.
 */
final class AnalyticsFields
{
    private const AUTHORS = [
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システムID',
        'id' => 'レコード番号',
    ];

    public const PAST_SALES = [
        'salesperson' => '担当者',
        'flag' => '異常値フラグ',
        'fiscal_month_start' => '決算最終月初',
        'fiscal_year' => '決算年度',
        'subtotal' => '小計',
        'total' => '年間合計',
        'record_key' => 'レコードキー',
        'date_label' => '年月日',
        'year_number' => '年_日付',
        'date_number' => '年月日_日付',
        'era' => '日付_和暦',
        ...self::AUTHORS,
    ];

    public const SALES_PLAN = [
        'customer_id' => '得意先ID',
        'customer_code' => '得意先コード',
        'legacy_customer_code' => '旧得意先コード',
        'customer_name' => '得意先名',
        'partner_group' => 'グループ名',
        'segment' => '分類',
        'sub_category' => '中分類',
        'planned_on' => '日付',
        'year' => '年',
        'plan_total' => '計画値',
        'actual_total' => '年間合計',
        'progress' => '計画比進捗率',
        'plans' => '▼ 計画値',
        'actuals' => '▼ 実績',
        'notes' => '備考',
        ...self::AUTHORS,
    ];

    /** The label of a month column: 「5月」 (22) or 「05月」 (25). */
    public static function month(string $column, bool $padded): string
    {
        $month = (int) substr($column, -2);

        return ($padded ? str_pad((string) $month, 2, '0', STR_PAD_LEFT) : (string) $month).'月';
    }
}
