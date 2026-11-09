<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アプリ名 set by an administrator (アプリの設定 → 設定, user 2026-11-09): such a name is kept when the registry follows the
 * code (AppLifecycle::syncWithCode), which otherwise gives every app the name its screens are built with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->boolean('name_customized')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropColumn('name_customized');
        });
    }
};
