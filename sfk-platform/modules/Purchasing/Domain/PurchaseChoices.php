<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain;

/**
 * Choices of the purchasing apps exactly as in the legacy apps (SYSTEM_BRAIN APP_05 / APP_07 fields.md), stored as
 * their labels.
 */
final class PurchaseChoices
{
    /** 仕入原価超過 of 05 (BR-PU-05). */
    public const OVERRUNS = ['用紙', '印刷', 'トムソン', '刷版'];

    /** 帳端処理 of 05: a single check 「〇」. */
    public const PERIOD_END = '〇';

    /** FSC コードNo (BR-OR-12). */
    public const FSC_CODE = '115';

    /** 金種名 of 07 (default 7.振込); 4.値引 / 5.相殺 settle without cash. */
    public const PAYMENT_METHODS = ['1.現金', '2.小切手', '3.手形', '4.値引', '5.相殺', '6.その他', '7.振込'];

    public const DEFAULT_PAYMENT_METHOD = '7.振込';

    /**
     * @param  list<string>  $choices
     * @return array<string, string>
     */
    public static function options(array $choices): array
    {
        return array_combine($choices, $choices);
    }
}
