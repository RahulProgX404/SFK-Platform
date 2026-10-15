<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

/**
 * For backed enums whose value is the legacy label: value => label options for selects.
 */
trait HasOptions
{
    public function label(): string
    {
        return $this->value;
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
