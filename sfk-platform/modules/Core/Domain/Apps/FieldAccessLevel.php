<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Apps;

/** What a user may do with a field (フィールドのアクセス権): 編集, 閲覧 (shown, not changed) or なし (not shown). */
enum FieldAccessLevel: string
{
    case Edit = 'edit';
    case View = 'view';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Edit => '編集',
            self::View => '閲覧',
            self::None => 'なし',
        };
    }

    public function canView(): bool
    {
        return $this !== self::None;
    }

    public function canEdit(): bool
    {
        return $this === self::Edit;
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
