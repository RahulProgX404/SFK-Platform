<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Search\SearchQuery;

/**
 * The list search (Modules\Core\Application\Search\SearchQuery) on PostgreSQL: pg_trgm for the trigram indexes of the
 * searched columns (SearchIndex, in the modules' migrations) and sfk_search_norm(text) — NFKC and lower case, the same
 * steps as SearchText::fold(). SQLite gets the function from PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SearchQuery::isPostgres()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(SearchQuery::postgresFunction());
    }

    public function down(): void
    {
        if (SearchQuery::isPostgres()) {
            DB::statement('DROP FUNCTION IF EXISTS '.SearchQuery::FUNCTION.'(text) CASCADE');
        }
    }
};
