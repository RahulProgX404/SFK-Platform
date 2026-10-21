<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Employees;

/**
 * Field labels of 02. 従業員マスタ exactly as in the legacy system (SYSTEM_BRAIN APP_02 fields.md), by attribute of the
 * user record that holds them. 残業単価 is not in the legacy app; it belongs to the 従業員マスタ情報 of ユーザー管理.
 */
final class EmployeeFields
{
    public const LABELS = [
        'name' => '従業員名',
        'name_kana' => 'ふりがな',
        'employee_categories' => '従業員カテゴリ',
        'sales_staff_code' => '担当者コード',
        'worker_code' => '作業員コード',
        'department_code' => '部課コード',
        'employment_status' => '在籍状況',
        'notes' => '備考',
        'id' => '従業員ID',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
        'work_rate' => '作業単価',
        'overtime_rate' => '残業単価',
    ];

    /** Confidential hourly rates (APP_02 開発者用フィールド; Core EmployeeDataAccess). */
    public const RATES = ['work_rate', 'overtime_rate'];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }

    /** 作業単価 / 残業単価: integer yen per hour, shown like the legacy decimal field (unit ¥). */
    public static function formatRate(mixed $state): string
    {
        return is_numeric($state) ? '¥'.number_format((int) $state) : '';
    }
}
