<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Application\Search\SearchIndex;

/**
 * Trigram indexes for the list search of the Sales apps (SearchIndex, PostgreSQL only): the columns the apps search by
 * default, in the normalized form the search compares (sfk_search_norm).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'sales' => ['sales_number', 'product_name', 'summary'],
        'delivery_notes' => ['number', 'notes', 'address'],
        'invoices' => ['number', 'notes', 'address'],
        'receipts' => ['notes'],
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
