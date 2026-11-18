<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 社内メッセージ upgrade (docs/messaging.md): channels, priorities, per-conversation notification levels, the order of
 * pinned conversations, availability, announcement priority / expiry, company settings and the admin audit log.
 * Additive — every existing conversation, message and announcement keeps its data (an announcement's 重要 flag becomes the
 * priority 重要).
 */
return new class extends Migration
{
    public function up(): void
    {
        // channels (部署・プロジェクト): archived by an administrator, still readable, no new messages
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            // 通知: all = every message, mentions = only @ / replies / 重要以上, none = nothing (with or without muted_until)
            $table->string('notification_level', 10)->default('all');
            // the user's own order of their pinned conversations (drag)
            $table->unsignedInteger('pin_order')->nullable();
        });

        Schema::table('messages', function (Blueprint $table): void {
            // 0 通常, 1 重要, 2 至急
            $table->unsignedTinyInteger('priority')->default(0);
        });

        Schema::table('messaging_preferences', function (Blueprint $table): void {
            // 在席状況 set by the user: auto (online / away from activity), away, dnd (取り込み中)
            $table->string('status', 10)->default('auto');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('priority', 10)->default('normal');
            $table->timestamp('expires_at')->nullable()->index();
        });
        // the old flag was shown as 重要: it becomes the priority 重要
        DB::table('announcements')->where('urgent', true)->update(['priority' => 'important']);
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn('urgent');
        });

        // company settings of the messenger (メッセージ管理), over config/messaging
        Schema::create('messaging_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // what administrators did (channels, members, moderation, settings) — never message contents
        Schema::create('messaging_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->foreignId('conversation_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_audit_logs');
        Schema::dropIfExists('messaging_settings');
        Schema::table('announcements', function (Blueprint $table): void {
            $table->boolean('urgent')->default(false);
        });
        DB::table('announcements')->whereIn('priority', ['important', 'urgent'])->update(['urgent' => true]);
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['priority', 'expires_at']);
        });
        Schema::table('messaging_preferences', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('priority'));
        Schema::table('conversation_participants', fn (Blueprint $table) => $table->dropColumn(['notification_level', 'pin_order']));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
