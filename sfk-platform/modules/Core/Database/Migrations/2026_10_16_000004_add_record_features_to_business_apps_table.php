<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アプリの設定 → 設定 → 高度な設定 → その他の設定 (legacy app settings): 「レコードの再利用」 and 「変更履歴」 per app,
 * both enabled by default like in the legacy system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->boolean('record_duplicate_enabled')->default(true);
            $table->boolean('record_history_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropColumn(['record_duplicate_enabled', 'record_history_enabled']);
        });
    }
};
