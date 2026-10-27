<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Application\Search\SearchIndex;

/**
 * Trigram indexes for the list search of the MasterData apps (SearchIndex, PostgreSQL only): the columns the apps search by
 * default, in the normalized form the search compares (sfk_search_norm).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'partners' => ['name', 'code', 'legacy_customer_code', 'name_kana', 'short_name'],
        'machines' => ['code', 'name'],
        'consumables' => ['name', 'notes', 'unit'],
        'users' => ['name', 'last_name_kana', 'first_name_kana', 'sales_staff_code', 'worker_code', 'department_code'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            SearchIndex::create($table, $columns);
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            SearchIndex::drop($table, $columns);
        }
    }
};
