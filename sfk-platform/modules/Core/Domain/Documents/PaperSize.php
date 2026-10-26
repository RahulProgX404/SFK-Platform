<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/** 用紙 of a 帳票テンプレート without a background PDF (白紙), in mm. */
enum PaperSize: string
{
    case A4Portrait = 'a4_portrait';
    case A4Landscape = 'a4_landscape';
    case A3Portrait = 'a3_portrait';
    case A3Landscape = 'a3_landscape';
    case B4Portrait = 'b4_portrait';
    case B5Portrait = 'b5_portrait';
    case Postcard = 'postcard';

    public function label(): string
    {
        return match ($this) {
            self::A4Portrait => 'A4 縦',
            self::A4Landscape => 'A4 横',
            self::A3Portrait => 'A3 縦',
            self::A3Landscape => 'A3 横',
            self::B4Portrait => 'B4 縦',
            self::B5Portrait => 'B5 縦',
            self::Postcard => 'はがき',
        };
    }

    /** @return array{width: float, height: float} */
    public function size(): array
    {
        [$width, $height] = match ($this) {
            self::A4Portrait => [210, 297],
            self::A4Landscape => [297, 210],
            self::A3Portrait => [297, 420],
            self::A3Landscape => [420, 297],
            self::B4Portrait => [257, 364],
            self::B5Portrait => [182, 257],
            self::Postcard => [100, 148],
        };

        return ['width' => (float) $width, 'height' => (float) $height];
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
