<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Apps;

/**
 * Who an entry of the レコード / フィールド アクセス権 applies to. The entries of a rule are read top to bottom and the
 * first one that applies to the user decides (like the legacy system); 作成者 (the record's creator) exists for record
 * rules only.
 */
enum AccessSubject: string
{
    case Creator = 'creator';
    case User = 'user';
    case Category = 'category';
    case Everyone = 'everyone';

    public function label(): string
    {
        return match ($this) {
            self::Creator => '作成者',
            self::User => '個人',
            self::Category => '従業員カテゴリ',
            self::Everyone => '全員',
        };
    }

    /**
     * @param  bool  $withCreator  record rules (作成者 is a field of the record)
     * @return array<string, string> value => label
     */
    public static function options(bool $withCreator): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            if ($case !== self::Creator || $withCreator) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }
}
