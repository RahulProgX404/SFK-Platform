<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a user uses the apps (bug.md 2026-11-05): one row per user and app — how often its screens were opened (よく使うアプリ
 * of the アプリ panel), when last, and the record last opened in it (the dashboard's クイックアクション when nothing waits).
 * The user's own order of the apps in the アプリ panel is `users.app_order`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app_code', 64);
            $table->unsignedInteger('visits')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->string('record_type')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->timestamp('record_visited_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'app_code']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('app_order')->nullable(); // jsonb: PostgreSQL has no equality for json (SELECT DISTINCT users.*)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('app_order');
        });
        Schema::dropIfExists('app_usages');
    }
};
