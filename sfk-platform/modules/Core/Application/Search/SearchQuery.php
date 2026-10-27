<?php

declare(strict_types=1);

namespace Modules\Core\Application\Search;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Search\SearchText;
use PDO;

/**
 * The list search in SQL. A word of three characters or more matches when the column's folded form (SearchText::fold(),
 * in the database the function sfk_search_norm) contains one of the word's forms (SearchText::variants()); on
 * PostgreSQL the searched columns have trigram indexes on that form (SearchIndex), so such a search does not read the
 * whole table. A shorter word cannot use those indexes: it is compared with the column as stored, in lower case, in
 * each width and kana form (SearchText::shortVariants()) — as fast as a plain search. On SQLite (tests, quick look)
 * the function is PHP (registerFunction()).
 */
final class SearchQuery
{
    public const FUNCTION = 'sfk_search_norm';

    /**
     * Records whose $column contains the word, in any of its forms.
     *
     * @template TQuery of Builder<*>|QueryBuilder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function where(Builder|QueryBuilder $query, string $column, string $word, string $boolean = 'and'): Builder|QueryBuilder
    {
        // three characters or more: the folded form, trigram-indexed; shorter: the stored text, in each of its forms
        [$expression, $variants] = SearchText::isIndexed($word)
            ? [self::expression($query, $column), SearchText::variants($word)]
            : ['LOWER(CAST('.self::wrap($query, $column).' AS TEXT))', SearchText::shortVariants($word)];

        return $query->where(function (Builder|QueryBuilder $query) use ($expression, $variants): void {
            foreach ($variants as $variant) {
                $query->orWhereRaw($expression." LIKE ? ESCAPE '!'", ['%'.self::escapeLike($variant).'%']);
            }
        }, boolean: $boolean);
    }

    /**
     * A searchable(query: …) of a column shown from a related record (e.g. 売上先 → the customer's 取引先名).
     *
     * @return Closure(Builder<*>, string): Builder<*>
     */
    public static function through(string $relationship, string $column): Closure
    {
        return fn (Builder $query, string $search): Builder => $query->whereHas($relationship, fn (Builder $related) => self::where($related, $related->qualifyColumn($column), $search));
    }

    /**
     * The compared form of a column in SQL; the same expression the indexes are built on.
     *
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function expression(Builder|QueryBuilder $query, string $column): string
    {
        return self::FUNCTION.'(CAST('.self::wrap($query, $column).' AS TEXT))';
    }

    /** @param  Builder<*>|QueryBuilder  $query */
    private static function wrap(Builder|QueryBuilder $query, string $column): string
    {
        return ($query instanceof Builder ? $query->getQuery() : $query)->getGrammar()->wrap($column);
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** sfk_search_norm on SQLite (PostgreSQL has it from the migration). */
    public static function registerFunction(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        $fold = fn (mixed $text): ?string => $text === null ? null : SearchText::fold((string) $text);

        if (method_exists($pdo, 'createFunction')) {
            $pdo->createFunction(self::FUNCTION, $fold, 1, PDO::SQLITE_DETERMINISTIC);
        } else {
            $pdo->sqliteCreateFunction(self::FUNCTION, $fold, 1, PDO::SQLITE_DETERMINISTIC);
        }
    }

    /**
     * The SQL of the PostgreSQL function, for the migration: NFKC and lower case (SearchText::fold()). Kana are folded on
     * the side of the word (SearchText::variants()): a translate() of the column made a search read the table 6× slower.
     */
    public static function postgresFunction(): string
    {
        return 'CREATE OR REPLACE FUNCTION '.self::FUNCTION.'(text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE AS '
            .'$$ SELECT lower(normalize($1, NFKC)) $$';
    }

    public static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
