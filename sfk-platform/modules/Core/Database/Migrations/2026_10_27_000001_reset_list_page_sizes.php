<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 表示件数 starts at 100件 for everyone again (the default, AppListRecords::DEFAULT_PAGE_SIZE): choices saved while
 * the lists were tried out are dropped once. Users can still pick another size in 「…」 → 表示件数.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('list_page_size')->update(['list_page_size' => null]);
    }

    public function down(): void
    {
        // the dropped choices are not kept
    }
};
