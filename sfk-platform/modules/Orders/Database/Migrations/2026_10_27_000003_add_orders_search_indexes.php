<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Application\Search\SearchIndex;

/**
 * Trigram indexes for the list search of the Orders apps (SearchIndex, PostgreSQL only): the columns the apps search by
 * default, in the normalized form the search compares (sfk_search_norm).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'orders' => ['order_number', 'product_name'],
        'quotations' => ['quote_number', 'product_name', 'size', 'paper', 'printing', 'window', 'flap_glue', 'notes', 'free_text_1', 'free_text_2', 'free_text_3'],
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
