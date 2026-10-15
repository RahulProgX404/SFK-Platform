<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

/** 分類 (SYSTEM_BRAIN APP_01): the legacy options exactly; they mix numbers and names (C-12). */
final class PartnerSegment
{
    public const OPTIONS = [
        '1', '2', '3', '4', '5', '6', '7', '8',
        'DNP', '凸版', 'アド印刷', 'ヤマガタ', 'タガヤ', '麗都商事', '大阪商工信金', '西日本住宅', '東日本住宅',
    ];

    /** @return array<int|string, string> value => label (PHP turns the numeric values '1'…'8' into int keys) */
    public static function options(): array
    {
        return array_combine(self::OPTIONS, self::OPTIONS);
    }
}
