<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Application\Search\SearchIndex;

/**
 * 12. 製袋予定管理 as the 製袋予定表 board (production-schedule.js, user 2026-11-06): a schedule is a day on a line (機械)
 * with 担当者 / 注番 / 品名 / 部数 / 備考 and a background ハイライト.
 *
 * - `scheduled_on`: the day of the board, taken from 開始日時 for the records entered before (they keep their times).
 * - `person_initial` / `product_name`: 担当者 / 品名 as saved — for a 注番 they come from 06. 売上入力.
 * - `highlight_color`: the key of the colour (yellow, rose …) or none.
 * - `notes`: 備考 are now up to 10 notes (one per line).
 * - list search over 品名 / 担当者 (SearchIndex, PostgreSQL).
 * The board replaces the 日次 / 週次 / 月次 / 年次ガントチャート views; their stored rows become the one 製袋予定表 view.
 */
return new class extends Migration
{
    private const BOARD_VIEW = '製袋予定表';

    public function up(): void
    {
        Schema::table('bag_schedules', function (Blueprint $table): void {
            $table->date('scheduled_on')->nullable()->after('legacy_id');
            $table->string('person_initial', 40)->nullable()->after('machine_id');
            $table->string('product_name', 255)->nullable()->after('order_id');
            $table->string('highlight_color', 16)->nullable()->after('notes');
            $table->index(['scheduled_on', 'machine_id']);
        });

        Schema::table('bag_schedules', function (Blueprint $table): void {
            $table->text('notes')->nullable()->change();
        });

        DB::table('bag_schedules')->whereNotNull('starts_at')->update(['scheduled_on' => DB::raw('date(starts_at)')]);
        SearchIndex::create('bag_schedules', ['product_name', 'person_initial']);

        $this->replaceGanttViews();
    }

    public function down(): void
    {
        SearchIndex::drop('bag_schedules', ['product_name', 'person_initial']);
        Schema::table('bag_schedules', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_on', 'machine_id']);
            $table->dropColumn(['scheduled_on', 'person_initial', 'product_name', 'highlight_color']);
        });
    }

    /** The stored Gantt views of the app (already shown once) become the 製袋予定表 view, first in the list. */
    private function replaceGanttViews(): void
    {
        if (! Schema::hasTable('business_apps') || ! Schema::hasTable('app_list_views')) {
            return;
        }

        $appId = DB::table('business_apps')->where('code', 'bag-schedule')->value('id');
        if ($appId === null) {
            return;
        }

        $views = DB::table('app_list_views')->where('business_app_id', $appId);
        $gantt = (clone $views)->whereIn('custom_view', ['gantt-day', 'gantt-week', 'gantt-month', 'gantt-year']);
        if (! $gantt->exists()) {
            return;
        }

        $gantt->delete();
        if (! (clone $views)->where('name', self::BOARD_VIEW)->exists()) {
            DB::table('app_list_views')->insert([
                'business_app_id' => $appId,
                'name' => self::BOARD_VIEW,
                'type' => 'custom',
                'columns' => json_encode([]),
                'custom_view' => 'board',
                'filter' => json_encode(['match' => 'and', 'conditions' => [], 'sorts' => []]),
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
