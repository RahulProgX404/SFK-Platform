<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 財務分析を表示 (user, 2026-11-05): who sees the money of the dashboard (売上・入出金 cards) and 分析 → 売上分析. Set by
 * administrators on the user's settings; the administrators of today start with it, super admins always see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('show_financial_analysis')->default(false);
        });

        DB::table('users')->whereIn('user_type', ['admin', 'super_admin'])->update(['show_financial_analysis' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('show_financial_analysis');
        });
    }
};
