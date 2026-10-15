<?php

declare(strict_types=1);

namespace Modules\Core\Domain\People;

/** 性別, shown and edited on the profile page and in user management. */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => '男性',
            self::Female => '女性',
            self::Other => 'その他',
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
