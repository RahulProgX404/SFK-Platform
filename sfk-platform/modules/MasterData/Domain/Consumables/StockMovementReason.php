<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Consumables;

/**
 * Why the stock of a consumable moved. 入庫 adds, 使用 (daily reports, 08N 使用数) takes out, 棚卸調整 sets the counted
 * difference (either sign).
 */
enum StockMovementReason: string
{
    case Receipt = 'receipt';
    case Usage = 'usage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => '入庫',
            self::Usage => '使用',
            self::Adjustment => '棚卸調整',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
