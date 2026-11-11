<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The versions of what the screens keep in the cache (Modules\Core\Application\Cache\CacheVersions, speed — user
 * 2026-11-11): `reference` (registry, access rights, 一覧, 帳票) and `data` (everything the dashboard adds up). They live
 * in the database, not in the cache, because local development and Render share the database but not the cache: a
 * change made on one side must make the other read afresh too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_versions', function (Blueprint $table): void {
            $table->string('name', 32)->primary();
            $table->string('version', 32);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_versions');
    }
};
