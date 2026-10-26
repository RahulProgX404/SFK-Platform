<?php

/*
 * Every foreign key has an index led by its column: the database does not add one by itself (PostgreSQL, SQLite), and
 * without it a record's rows, related lists and deletes of a parent read the whole child table.
 */

use Illuminate\Support\Facades\Schema;

it('indexes every foreign key', function () {
    $missing = [];
    foreach (array_column(Schema::getTables(), 'name') as $table) {
        $leading = array_map(fn (array $index): ?string => $index['columns'][0] ?? null, Schema::getIndexes($table));

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($foreignKey['columns'][0], $leading, true)) {
                $missing[] = $table.'.'.$foreignKey['columns'][0];
            }
        }
    }

    expect($missing)->toBe([]);
});
