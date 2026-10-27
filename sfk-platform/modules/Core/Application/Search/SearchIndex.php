<?php

declare(strict_types=1);

namespace Modules\Core\Application\Search;

use Illuminate\Support\Facades\DB;

/**
 * Trigram indexes (pg_trgm, GIN) on the compared form of the columns the list search looks in, for the migrations of
 * the modules: `SearchIndex::create('sales', ['sales_number', 'product_name'])`. PostgreSQL only; nothing on SQLite.
 * Every column an app list searches by default needs one (SearchIndexTest).
 */
final class SearchIndex
{
    /** @param  list<string>  $columns */
    public static function create(string $table, array $columns): void
    {
        if (! SearchQuery::isPostgres()) {
            return;
        }

        foreach ($columns as $column) {
            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s USING gin (%s(CAST(%s AS TEXT)) gin_trgm_ops)',
                self::name($table, $column),
                DB::getQueryGrammar()->wrapTable($table),
                SearchQuery::FUNCTION,
                DB::getQueryGrammar()->wrap($column),
            ));
        }
    }

    /** @param  list<string>  $columns */
    public static function drop(string $table, array $columns): void
    {
        if (! SearchQuery::isPostgres()) {
            return;
        }

        foreach ($columns as $column) {
            DB::statement('DROP INDEX IF EXISTS '.self::name($table, $column));
        }
    }

    public static function name(string $table, string $column): string
    {
        $name = $table.'_'.$column.'_search';

        return strlen($name) <= 63 ? $name : substr($name, 0, 50).'_'.substr(md5($name), 0, 12);
    }
}
