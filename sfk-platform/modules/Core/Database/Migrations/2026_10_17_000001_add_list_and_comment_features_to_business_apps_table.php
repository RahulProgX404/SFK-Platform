<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アプリの設定 → 設定 → 高度な設定 → その他の設定 (legacy app settings): インライン編集 (pencil and × on the list rows),
 * コメント機能 and 一括削除 per app, enabled by default like in the legacy app templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->boolean('inline_edit_enabled')->default(true);
            $table->boolean('record_comments_enabled')->default(true);
            $table->boolean('bulk_delete_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropColumn(['inline_edit_enabled', 'record_comments_enabled', 'bulk_delete_enabled']);
        });
    }
};
