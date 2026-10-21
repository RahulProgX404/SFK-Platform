<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カスタマイズ形式 list views (ListViewType::Custom): the app's screen a view shows (AppListRecords::customListViews(),
 * e.g. the Gantt charts of 12. 製袋予定管理).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_list_views', function (Blueprint $table): void {
            $table->string('custom_view', 64)->nullable()->after('title_field');
        });
    }

    public function down(): void
    {
        Schema::table('app_list_views', function (Blueprint $table): void {
            $table->dropColumn('custom_view');
        });
    }
};
