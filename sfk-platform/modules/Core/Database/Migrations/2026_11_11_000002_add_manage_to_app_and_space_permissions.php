<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アプリ管理 (user, 2026-11-11; AppAbility::Manage): a row of an app's or a space's アクセス権 may make its users the
 * app's administrators — every other ability plus what only administrators may do in the app. No row has it yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['app_permissions', 'space_permissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('can_manage')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['app_permissions', 'space_permissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('can_manage');
            });
        }
    }
};
