<?php

declare(strict_types=1);

namespace Modules\Core\Domain\People;

/**
 * 従業員カテゴリ, preserved from the kintone employee master (SYSTEM_BRAIN APP_02, incl. the
 * disabled option 役員).
 */
enum EmployeeCategory: string
{
    case Sales = 'sales';
    case GeneralAffairs = 'general_affairs';
    case Manufacturing = 'manufacturing';
    case Engineering = 'engineering';
    case Design = 'design';
    case ManufacturingPartTime = 'manufacturing_part_time';
    case SalesPartTime = 'sales_part_time';
    case Executive = 'executive';

    public function label(): string
    {
        return match ($this) {
            self::Sales => '営業',
            self::GeneralAffairs => '総務',
            self::Manufacturing => '製造',
            self::Engineering => '工務',
            self::Design => '制作',
            self::ManufacturingPartTime => '製造パート',
            self::SalesPartTime => '営業パート',
            self::Executive => '役員',
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
