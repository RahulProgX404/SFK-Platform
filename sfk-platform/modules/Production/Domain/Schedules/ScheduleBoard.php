<?php

declare(strict_types=1);

namespace Modules\Production\Domain\Schedules;

/**
 * The fixed parts of the 製袋予定表 (12. 製袋予定管理, production-schedule.js): its lines — one row / section each, in this
 * order, each a machine of 03. 機械マスタ by its 機械コード —, the background highlight colours and the 注番 format
 * (123 → sfk-0000123). 6号機 asks to consult the 工場長 instead of showing an empty line.
 */
final class ScheduleBoard
{
    /**
     * @var list<array{code: string, label: string, subtitle?: string, color: string, soft: string, managerCheck?: string}>
     */
    public const LINES = [
        ['code' => 'K00001', 'label' => '1号機', 'color' => '#0b489d', 'soft' => '#dceaf6'],
        ['code' => 'K00002', 'label' => '2号機', 'color' => '#3b6ea5', 'soft' => '#e5edf6'],
        ['code' => 'K00005', 'label' => '5号機', 'subtitle' => '175幅まで', 'color' => '#666f6c', 'soft' => '#eceae1'],
        ['code' => 'K00006', 'label' => '6号機', 'color' => '#b8791f', 'soft' => '#f7ecd3', 'managerCheck' => '予定を入れる際は必ず工場長に相談して下さい'],
        ['code' => 'K00003', 'label' => '3号機', 'subtitle' => '245幅まで', 'color' => '#7c5cbf', 'soft' => '#efe9f8'],
        ['code' => 'K00004', 'label' => '3-2号機・4号機', 'subtitle' => '310幅まで', 'color' => '#b25a7a', 'soft' => '#f7e9ee'],
        ['code' => 'K00010', 'label' => 'カマス3号機', 'subtitle' => 'ダイア／ミシン／内・外カマス', 'color' => '#2f8f5b', 'soft' => '#e1f4e9'],
        ['code' => 'K00009', 'label' => 'カマス2号機', 'subtitle' => 'ダイア／内カマス／間欠　※外カマス不可', 'color' => '#1f6f8c', 'soft' => '#e1eff3'],
        ['code' => 'K00008', 'label' => 'カマス1号機', 'subtitle' => '内・外カマス　※ダイア不可', 'color' => '#8c5a2f', 'soft' => '#f3e9e1'],
        ['code' => 'K00011', 'label' => 'ロファ窓', 'subtitle' => 'FWC20', 'color' => '#5a6b8c', 'soft' => '#e7eaf1'],
        ['code' => 'K00034', 'label' => 'ロファ窓', 'subtitle' => 'S3', 'color' => '#6b8c5a', 'soft' => '#eaf1e7'],
        ['code' => 'K00013', 'label' => '窓131', 'color' => '#8c2f6b', 'soft' => '#f3e1ec'],
        ['code' => 'K00016', 'label' => 'アラビア', 'color' => '#2f5a8c', 'soft' => '#e1e9f3'],
        ['code' => 'K00014', 'label' => 'アドヘア', 'color' => '#6b2f8c', 'soft' => '#ece1f3'],
        ['code' => 'K00007', 'label' => '平台', 'subtitle' => '440幅まで', 'color' => '#8c6b2f', 'soft' => '#f3ece1'],
        ['code' => 'K00015', 'label' => 'テープ/HM', 'subtitle' => '※外カマス不可', 'color' => '#2f8c7d', 'soft' => '#e1f3f0'],
        ['code' => 'K00017', 'label' => '手差しTP/内職', 'color' => '#8c2f2f', 'soft' => '#f3e1e1'],
    ];

    /** Background highlight of a schedule: key => label (黄 / 赤 / 橙 / 緑 / 青 / 紫). */
    public const HIGHLIGHTS = ['yellow' => '黄', 'rose' => '赤', 'orange' => '橙', 'green' => '緑', 'blue' => '青', 'purple' => '紫'];

    public const ORDER_PREFIX = 'sfk-';

    public const ORDER_DIGITS = 7;

    public const MAX_NOTES = 10;

    /** 注番 typed as digits ("123") → "sfk-0000123"; null when it is not 1–7 digits. */
    public static function orderNumber(string $input): ?string
    {
        $digits = trim($input);

        return preg_match('/^\d{1,'.self::ORDER_DIGITS.'}$/', $digits) === 1
            ? self::ORDER_PREFIX.str_pad($digits, self::ORDER_DIGITS, '0', STR_PAD_LEFT)
            : null;
    }

    /** Whether the value is a stored 注番 ("sfk-0000123"). */
    public static function isOrderNumber(string $value): bool
    {
        return preg_match('/^'.preg_quote(self::ORDER_PREFIX, '/').'\d{'.self::ORDER_DIGITS.'}$/', $value) === 1;
    }

    /** @return list<string> the 機械コード of the lines, in order */
    public static function codes(): array
    {
        return array_column(self::LINES, 'code');
    }
}
