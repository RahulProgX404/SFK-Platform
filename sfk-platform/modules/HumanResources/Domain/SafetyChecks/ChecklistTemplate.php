<?php

declare(strict_types=1);

namespace Modules\HumanResources\Domain\SafetyChecks;

/**
 * The checklist of 17. 安全衛生チェック. The legacy item texts were never exported (SYSTEM_BRAIN 15_UNKNOWN U-13); what is
 * known is the numbering of the legacy form label (BR-HR-05): 営業 fill 1–6, 14, 15 and 19, drivers fill 20–25 — and
 * that every row of the table needed its チェック. The 25 items below follow that numbering as a standard monthly
 * workplace self-check (労働安全衛生):
 *
 *   1–6   everyone: order, passages, clothing, health, near misses, hazards
 *   7–13  work at the machines and in the factory
 *   14–15 posture, breaks and screen work (office work, 営業 too)
 *   16–18 materials, electricity, stacking (factory)
 *   19    mental health (everyone)
 *   20–25 driving (drivers; 営業 drive the company cars)
 *
 * Replace the texts when the real list is exported from the legacy records; the numbering stays.
 */
final class ChecklistTemplate
{
    /** No => 項目 */
    public const ITEMS = [
        1 => '整理・整頓・清掃ができている',
        2 => '通路・非常口・消火器の前に物を置いていない',
        3 => '作業に適した服装・身だしなみである',
        4 => '体調は良好で、睡眠は十分にとれている',
        5 => 'ヒヤリ・ハットがあれば報告している',
        6 => '危険な箇所・設備の不具合を見つけたら報告している',
        7 => '機械の安全カバー・非常停止装置が正常に機能している',
        8 => '機械の始業点検を行っている',
        9 => '機械の清掃・調整・紙詰まりの処理は機械を止めてから行っている',
        10 => '断裁機・刃物の取扱い手順を守っている',
        11 => '保護具（手袋・保護メガネ・耳栓等）を正しく着用している',
        12 => 'フォークリフト・台車は安全に運転・使用している',
        13 => '重量物は正しい姿勢で持ち運んでいる',
        14 => '長時間同じ姿勢を続けず、適度に休憩をとっている',
        15 => 'パソコン作業時の姿勢・画面の明るさ・照明は適切である',
        16 => 'インキ・糊・溶剤の保管と表示は適切である',
        17 => '電気配線・コンセント・延長コードに異常はない',
        18 => '製品・資材の積み上げは安定していて崩れる心配がない',
        19 => '悩みやストレスを抱え込まず、相談できている',
        20 => '運転前に車両の日常点検を行っている',
        21 => '運転前・運転後のアルコールチェックを受けている',
        22 => 'シートベルトを着用し、法定速度を守っている',
        23 => '運転中に携帯電話を使用していない',
        24 => '十分な車間距離をとり、安全運転を心がけている',
        25 => '駐車時は所定の場所に停め、施錠している',
    ];

    /** The items 営業 fill (BR-HR-05). */
    public const SALES = [1, 2, 3, 4, 5, 6, 14, 15, 19];

    /** The items drivers fill (BR-HR-05). */
    public const DRIVING = [20, 21, 22, 23, 24, 25];

    public const SALES_DEPARTMENT = '営業';

    /**
     * The items of a department: 営業 their items and driving; everyone else 1–19 (drivers add 20–25 by their No).
     *
     * @return list<int>
     */
    public static function numbersFor(?string $department): array
    {
        return $department === self::SALES_DEPARTMENT
            ? [...self::SALES, ...self::DRIVING]
            : array_values(array_diff(array_keys(self::ITEMS), self::DRIVING));
    }

    public static function item(int $number): ?string
    {
        return self::ITEMS[$number] ?? null;
    }
}
