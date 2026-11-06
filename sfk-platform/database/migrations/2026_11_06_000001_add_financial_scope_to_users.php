<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 財務分析の範囲 (bug.md 2026-11-06), next to 財務分析を表示: 全体 (overall — the dashboard shows everyone's money and
 * the user may switch to their own) or 個人のみ (personal — only the sales the user is the 担当者 of). Everyone who
 * sees the money today keeps seeing all of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('financial_scope', 16)->default('overall');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('financial_scope');
        });
    }
};
