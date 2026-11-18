<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Application\Search\SearchIndex;

/**
 * 社内メッセージ (Messaging module, docs/messaging.md).
 *
 * Read state is kept as watermarks on the participant (last read / delivered message id), not one row per message and
 * reader: a group of 100 then writes one row when a member reads, and "read by N" is counted from the members. The
 * conversation keeps a pointer to its newest message for the inbox (a pointer, not a total). Messages are ordered by
 * their id (bigint, assigned in commit order of the insert) — the history pages by keyset (`id < ?`), never OFFSET.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20); // direct | group
            $table->string('name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            // "<smaller user id>:<larger user id>" of a direct conversation: one per pair of users
            $table->string('direct_key', 50)->nullable()->unique();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // admin | member
            $table->foreignId('added_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            // left or removed: no access any more (the row stays for the audit and a later re-add)
            $table->timestamp('left_at')->nullable();
            $table->foreignId('removed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            // the member sees messages after this id only (added to a group that does not share its history)
            $table->unsignedBigInteger('visible_from_message_id')->nullable();
            // 「トークを削除」: the member's own copy of the history up to this id is cleared
            $table->unsignedBigInteger('cleared_message_id')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_delivered_message_id')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('favorite_at')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // the sender; null for the system lines of a group (「…さんが参加しました」)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // text | image | file | voice | sticker | system
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('reply_to_id')->nullable()->index()->constrained('messages')->nullOnDelete();
            $table->foreignId('forwarded_from_id')->nullable()->index()->constrained('messages')->nullOnDelete();
            // the sender's own id of the message: a resent request (double tap, network retry) stores nothing twice
            $table->uuid('client_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('unsent_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->unique(['user_id', 'client_id']);
        });
        SearchIndex::create('messages', ['body']);

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->index('last_message_id');
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // null while uploaded and not yet sent
            $table->foreignId('message_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('kind', 10); // image | file | voice
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'kind', 'id']);
        });
        SearchIndex::create('message_attachments', ['original_name']);

        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            // one reaction per person and message (a new one replaces it)
            $table->unique(['message_id', 'user_id']);
        });

        // 「自分のみ削除」: a message hidden from one member's history
        Schema::create('message_hides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'message_id']);
        });

        // the audit of edits and 送信取消: the text before the change
        Schema::create('message_edits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('action', 10); // edit | unsend
            $table->text('body')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('message_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'message_id']);
        });

        Schema::create('message_pins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('pinned_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['conversation_id', 'message_id']);
        });

        // company stickers added by administrators (the built-in ones are drawn by the messenger itself)
        Schema::create('messaging_stickers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('messaging_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('sound')->default(true);
            $table->boolean('desktop')->default(false);
            // the text of a message in toasts and browser notifications (off: only who wrote)
            $table->boolean('preview')->default(false);
            // オンライン / 最終アクセス shown to others
            $table->boolean('presence')->default(true);
            $table->timestamp('dnd_until')->nullable();
            $table->timestamps();
        });

        // 全社アナウンス: published by administrators to everyone, some 従業員カテゴリ or some employees
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('urgent')->default(false);
            $table->boolean('requires_confirmation')->default(false);
            $table->string('audience', 20); // all | categories | users
            $table->json('audience_ids')->nullable();
            $table->timestamp('published_at')->index();
            // when the recipients were notified (a scheduled announcement: once its time came, messaging:announcements)
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->index()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        // the audience, fixed when the announcement is published (one row per recipient), with 既読 / 確認
        Schema::create('announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('messaging_preferences');
        Schema::dropIfExists('messaging_stickers');
        Schema::dropIfExists('message_pins');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_edits');
        Schema::dropIfExists('message_hides');
        Schema::dropIfExists('message_reactions');
        SearchIndex::drop('message_attachments', ['original_name']);
        Schema::dropIfExists('message_attachments');
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropForeign(['last_message_id']);
        });
        SearchIndex::drop('messages', ['body']);
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
