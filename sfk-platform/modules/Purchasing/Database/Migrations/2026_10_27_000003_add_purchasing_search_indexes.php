<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Application\Search\SearchIndex;

/**
 * Trigram indexes for the list search of the Purchasing apps (SearchIndex, PostgreSQL only): the columns the apps search by
 * default, in the normalized form the search compares (sfk_search_norm).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'purchases' => ['purchase_number', 'product_name'],
        'consumable_orders' => ['notes'],
        'freight_charges' => ['carrier_number', 'notes'],
        'payment_lines' => ['notes'],
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
