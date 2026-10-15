<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Billing;

/** 回収予定日 of a customer (SYSTEM_BRAIN APP_01): 1日 … 31日, 末日 or 随時請求 (legacy labels). */
final class CollectionDay
{
    public const END_OF_MONTH = '末日';

    public const AD_HOC = '随時請求';

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (range(1, 31) as $day) {
            $options["{$day}日"] = "{$day}日";
        }

        return [...$options, self::END_OF_MONTH => self::END_OF_MONTH, self::AD_HOC => self::AD_HOC];
    }
}
