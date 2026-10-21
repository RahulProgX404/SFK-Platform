<?php

declare(strict_types=1);

namespace Modules\HumanResources\Domain;

/**
 * Field labels of 16. 申請管理, 17. 安全衛生チェック and 18. アルコールチェック exactly as in the legacy system (SYSTEM_BRAIN
 * APP_16 / APP_17 / APP_18 fields.md), by attribute.
 */
final class HrFields
{
    private const AUTHORS = [
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システムID',
    ];

    public const LEAVE = [
        'request_type' => '申請区分',
        'applicant_id' => '申請者',
        'worker_id' => '作業員名',
        'category' => '申請分類',
        'target_on' => '対象日付',
        'total_time' => '遅刻・早退_合計時間（30分単位）',
        'clock_time' => '遅刻・早退_出勤・退勤時間（30分単位）',
        'reason' => '申請理由',
        'status' => 'ステータス',
        'assignee' => '作業者',
        'id' => '申請管理ID',
        ...self::AUTHORS,
    ];

    public const SAFETY = [
        'department_machine' => '部署・機械',
        'department' => '部署',
        'user_id' => '名前',
        'employee_id' => '従業員名',
        'checked_on' => '日付',
        'instruction' => '※営業は1～6・14・15・19を記入してください※運転をしてる方は20～25を記入してください',
        'items' => 'チェックリスト',
        'number' => 'No',
        'item' => '項目',
        'answer' => '評価',
        'checked' => 'チェック',
        'notes' => '備考',
        'id' => 'レコード番号',
        ...self::AUTHORS,
    ];

    public const ALCOHOL = [
        'before' => '運転前',
        'after' => '運転後',
        'checked_on' => '検査日',
        'before_time' => '検査時刻',
        'driver_id' => '氏名',
        'employee_id' => '従業員名',
        'before_checker_id' => '確認者',
        'before_vehicle' => '車両番号',
        'before_method' => '確認方法',
        'before_detected' => '酒気帯び運転の有無',
        'before_instruction' => '指示項目',
        'before_notes' => 'その他必要な項目',
        'after_time' => '検査時刻',
        'after_vehicle' => '車両番号',
        'after_method' => '確認方法',
        'after_detected' => '酒気帯び運転の有無',
        'after_checker_id' => '確認者',
        'after_instruction' => '指示項目',
        'after_notes' => 'その他必要な項目',
        'id' => 'レコード番号',
        ...self::AUTHORS,
    ];

    /**
     * The label of an attribute; the 運転後 fields of 18 carry the same labels as the 運転前 ones, so lists and 絞り込む say
     * which block they belong to.
     */
    public static function alcohol(string $attribute, bool $qualified = false): string
    {
        $label = self::ALCOHOL[$attribute];

        if (! $qualified) {
            return $label;
        }

        return match (true) {
            str_starts_with($attribute, 'before_') => $label.'(運転前)',
            str_starts_with($attribute, 'after_') => $label.'(運転後)',
            default => $label,
        };
    }
}
