<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コメント redesign (comment-design.png, 2026-11-16): a reply sits under the comment it answers (parent_id — one level;
 * a deleted comment leaves its replies as comments of their own), 宛先 「全員」 (mentions_all) and 編集 by the writer
 * (edited_at, shown as 編集済み).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->index()->constrained('record_comments')->nullOnDelete();
            $table->boolean('mentions_all')->default(false);
            $table->timestamp('edited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('record_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['mentions_all', 'edited_at']);
        });
    }
};
