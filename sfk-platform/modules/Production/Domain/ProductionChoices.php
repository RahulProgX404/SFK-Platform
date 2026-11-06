<?php

declare(strict_types=1);

namespace Modules\Production\Domain;

/**
 * Choices of the production apps exactly as in the legacy apps (SYSTEM_BRAIN APP_08 / APP_12 / APP_14 fields.md), stored
 * as their labels.
 */
final class ProductionChoices
{
    /** 糊 of 08. */
    public const GLUES = ['F14', '両面テープ', 'ホットメルト', 'X9', 'X40 コート用', 'PP用 AV650', 'E2', 'SD35W', 'SD35Y', '和紙用　AV175', 'ボンドSP2850N 金銀', '糊なし'];

    /** 品質チェック of 08 (required, BR-PR-05). */
    public const QUALITY_CHECKS = ['前', '中', '後'];

    /** 手袋 of 08: a single check 「着用」. */
    public const GLOVES = '着用';

    /** ステータスの更新 of 08: a single check 「する」 (BR-PR-06). */
    public const UPDATE_STATUS = 'する';

    /** 難易度 of 08. */
    public const DIFFICULTIES = ['S 　非常に難しい', 'A　難しい', 'B　普通', 'C　やや簡単', 'D　簡単'];

    /** 開始時間 / 終了時間 / 休憩時間 of a new task of 08 (quick entry, 2026-11). */
    public const DEFAULT_START = '09:00';

    public const DEFAULT_END = '17:00';

    public const DEFAULT_BREAK = '00:00';

    /** 作業人数 of a new task of 08. */
    public const DEFAULT_CREW = '1';

    /** 日種別 of 08 (required). */
    public const DAY_TYPES = ['平日', '休日'];

    /** 勤務種別 of 08 (required, 通常 first): a 残業 task is overtime from 開始 to 終了 (TaskCosting::hours). */
    public const WORK_TYPES = ['通常', '残業'];

    public const WORK_OVERTIME = '残業';

    /** 使用状況 of 12, in the order of the legacy schedule; the colours of its Gantt chart (legacy app description). */
    public const USAGE_STATUSES = ['使用前' => '#ff9900', '使用中' => '#ff0000', '完了' => '#999999'];

    /** 入力方法 of 12. */
    public const ENTRY_FROM_ORDER = '受注から注番を取得';

    public const ENTRY_FREE = 'フリー入力';

    /** 項目 of the 損害 table of 14. */
    public const DAMAGE_ITEMS = ['紙', '印刷'];

    /**
     * @param  list<string>  $choices
     * @return array<string, string>
     */
    public static function options(array $choices): array
    {
        return array_combine($choices, $choices);
    }
}
