<?php

declare(strict_types=1);

namespace Modules\HumanResources\Domain;

/**
 * Choices of the HR apps exactly as in the legacy apps (SYSTEM_BRAIN APP_16 / APP_17 / APP_18 fields.md), stored as their
 * labels.
 */
final class HrChoices
{
    /** 申請区分 of 16. */
    public const REQUEST_TYPES = ['休暇申請', '早退申請', '遅刻申請', '振替申請'];

    /** 申請分類 of 16. */
    public const CATEGORIES = ['有給休暇', '半有給(午前有給)', '半有給(午後有給)', '振替休暇(代休)', '慶弔休暇', '休暇(欠勤)', '半休(午前欠勤)', '半休(午後欠勤)',
        '遅刻(時間欠勤)', '早退(時間欠勤)'];

    /** 部署 of 17. */
    public const DEPARTMENTS = ['製造', '製造パート', '営業', '制作', '事務', '工務'];

    /** 評価 of a checklist row of 17 (default はい). */
    public const ANSWERS = ['はい', 'いいえ'];

    /** チェック of a checklist row of 17: a single, required check 「✓」. */
    public const CHECK = '✓';

    /** 車両番号 of 18 (the company vehicles). */
    public const VEHICLES = ['さ　2-10（エポック）', 'に　2-10（ワゴン）', 'な　2-10（トラック）', 'つ　7-17'];

    /** 確認方法 of 18. */
    public const METHODS = ['対面', '電話', 'ビデオ通話'];

    /** 酒気帯び運転の有無 of 18. */
    public const DETECTED = ['有', '無'];

    /** 指示項目 defaults of 18. */
    public const BEFORE_INSTRUCTION = '安全点検確認して、安全運転、交通ルール守ること';

    public const AFTER_INSTRUCTION = '駐車場に停車、安全点検確認';

    /**
     * @param  list<string>  $choices
     * @return array<string, string>
     */
    public static function options(array $choices): array
    {
        return array_combine($choices, $choices);
    }
}
