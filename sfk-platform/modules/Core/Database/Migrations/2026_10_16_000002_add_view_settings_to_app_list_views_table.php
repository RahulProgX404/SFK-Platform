<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一覧 managed in アプリの設定 → 一覧 (legacy view settings): every view of an app is a row with its 表示形式
 * (表形式 with its own columns, or カレンダー形式 with 日付 / タイトル fields), 絞り込み and ソート; the topmost row is the
 * default view. The app's code-defined views are copied in once (list_views_seeded_at), then administrators own them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_list_views', function (Blueprint $table): void {
            $table->string('type', 16)->default('table')->after('name');
            $table->jsonb('columns')->nullable()->after('type');
            $table->string('date_field', 64)->nullable()->after('columns');
            $table->string('title_field', 64)->nullable()->after('date_field');
            $table->dropColumn('base_view');
        });

        Schema::table('business_apps', function (Blueprint $table): void {
            $table->timestamp('list_views_seeded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropColumn('list_views_seeded_at');
        });

        Schema::table('app_list_views', function (Blueprint $table): void {
            $table->dropColumn(['type', 'columns', 'date_field', 'title_field']);
            $table->string('base_view', 64)->nullable();
        });
    }
};
