<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Billing;

use Modules\Core\Domain\Concerns\HasOptions;

/** 回収予定区分 of a customer (SYSTEM_BRAIN APP_01): payment is collected this month, next month or the month after. */
enum CollectionMonth: string
{
    use HasOptions;

    case SameMonth = '当月';
    case NextMonth = '翌月';
    case MonthAfterNext = '翌々月';

    /** Months after the billing month. */
    public function monthsLater(): int
    {
        return match ($this) {
            self::SameMonth => 0,
            self::NextMonth => 1,
            self::MonthAfterNext => 2,
        };
    }
}
