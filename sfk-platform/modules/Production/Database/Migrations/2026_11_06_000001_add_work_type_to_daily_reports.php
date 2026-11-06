<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * 勤務種別 of 08. 日報入力 (通常 | 残業): a 残業 task is overtime from 開始 to 終了 (TaskCosting::hours). The entered
 * 残業開始時間 / 残業終了時間 / 休憩時間(残業) are no longer asked; their columns keep the values of the earlier records,
 * which are 通常 tasks with a separate overtime span and cost as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table): void {
            $table->string('work_type', 8)->default('通常')->after('day_type');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table): void {
            $table->dropColumn('work_type');
        });
    }
};
