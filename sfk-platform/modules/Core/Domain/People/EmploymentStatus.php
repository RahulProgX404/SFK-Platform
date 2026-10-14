<?php

declare(strict_types=1);

namespace Modules\Core\Domain\People;

/**
 * 在籍状況 of a person. Extends the kintone options 在籍中 / 休職中 / 退職済 (SYSTEM_BRAIN APP_02)
 * by splitting 退職済 into its reasons.
 */
enum EmploymentStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';
    case Retired = 'retired';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Active => '在籍中',
            self::OnLeave => '休職中',
            self::Resigned => '退職',
            self::Retired => '定年退職',
            self::Dismissed => '解雇',
        };
    }

    /** People who have left the company can no longer sign in. */
    public function canSignIn(): bool
    {
        return $this === self::Active || $this === self::OnLeave;
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
