<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboards (panel ids) granted to one user, in addition to those of their 従業員カテゴリ. Administrator-only
 * dashboards (共通管理) follow ユーザー種別 and are never stored here. jsonb for PostgreSQL DISTINCT support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('dashboards')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('dashboards');
        });
    }
};
