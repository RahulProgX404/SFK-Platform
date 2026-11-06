<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use Illuminate\Support\Facades\DB;

/** The calendar month (`YYYY-MM`) of a date column in SQL, for summing per month on PostgreSQL and SQLite. */
final class MonthBucket
{
    public static function sql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "to_char({$column}, 'YYYY-MM')";
    }

    /** @return array{0: int, 1: int} the year and month of `YYYY-MM` */
    public static function parse(string $value): array
    {
        [$year, $month] = explode('-', $value);

        return [(int) $year, (int) $month];
    }
}
