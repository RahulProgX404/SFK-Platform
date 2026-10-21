<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production (SYSTEM_BRAIN 08 日報入力, 12 製袋予定管理, 14 事故報告管理; BP-04, BR-PR-*).
 *
 * - `daily_reports`: one report per task (08. 日報入力: 「ひとつの作業に対して、ひとつのレコード登録」) with its overtime.
 *   It keeps the legacy lookup copies it is costed with — the worker's 作業単価 / 残業単価, the machine's rates and the box
 *   prices (snapshots taken when chosen) — and stores 作業時間 / 残業時間 / 社内原価 / 製造売上 so the job totals
 *   (製造済数量, 社内原価, 製造売上 of 04) are sums of the reports (BR-PR-01..03). Box usage is a stock movement of 19 per
 *   report (BR-PR-07), replaced on every save and removed with the report.
 * - `bag_schedules`: a machine booking of 12 (開始 / 終了日時, 使用状況), of a job (注番) or entered freely.
 * - `accident_reports` / `accident_damages` / `accident_report_user`: incidents of 14 with the damage table and the
 *   営業関係者; 合計損害額 is kept for lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('worked_on');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('work_rate')->nullable();
            $table->unsignedInteger('overtime_rate')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->restrictOnDelete();
            $table->foreignId('cardboard_id')->nullable()->constrained('consumables')->nullOnDelete();
            $table->decimal('cardboard_used', 16, 4)->nullable();
            $table->foreignId('partition_id')->nullable()->constrained('consumables')->nullOnDelete();
            $table->decimal('partition_used', 16, 4)->nullable();
            $table->string('glue', 32)->nullable();
            $table->decimal('glue_cost', 14, 4)->nullable();
            $table->string('started_at', 8)->nullable();
            $table->string('ended_at', 8)->nullable();
            $table->string('break_time', 8)->nullable();
            $table->decimal('changeover_minutes', 12, 4)->nullable();
            $table->decimal('changeover_count', 12, 4)->nullable();
            $table->decimal('produced', 16, 4)->nullable();
            $table->decimal('crew', 8, 4)->nullable();
            $table->string('day_type', 4)->default('平日');
            $table->string('overtime_started_at', 8)->nullable();
            $table->string('overtime_ended_at', 8)->nullable();
            $table->string('overtime_break', 8)->nullable();
            $table->jsonb('quality_checks')->nullable();
            $table->boolean('gloves')->default(false);
            $table->decimal('damaged_before', 16, 4)->nullable();
            $table->decimal('damaged_after', 16, 4)->nullable();
            $table->boolean('update_status')->default(false);
            $table->string('new_status', 32)->nullable();
            $table->string('difficulty', 16)->nullable();
            $table->text('notes')->nullable();
            // lookup copies (snapshots)
            $table->decimal('cardboard_cost', 14, 4)->nullable();
            $table->decimal('partition_cost', 14, 4)->nullable();
            foreach (['processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate', 'plate_change_rate', 'ink_mixing_rate'] as $rate) {
                $table->decimal($rate, 14, 4)->nullable();
            }
            // figures of the task (BR-PR-01..03)
            $table->decimal('work_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->nullable();
            $table->bigInteger('internal_cost')->default(0);
            $table->bigInteger('manufacturing_sales')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'worked_on']);
            $table->index('order_id');
        });

        Schema::create('bag_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('usage_status', 8)->nullable();
            $table->string('entry_mode', 16)->default('受注から注番を取得');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('free_order_number', 64)->nullable();
            $table->string('free_product_name')->nullable();
            $table->decimal('copies', 16, 4)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['machine_id', 'starts_at']);
        });

        Schema::create('accident_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('occurred_on');
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->restrictOnDelete();
            $table->foreignId('maker1_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maker2_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('cause')->nullable();
            $table->text('response')->nullable();
            $table->text('prevention')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('total_damage')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accident_damages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('item', 8)->nullable();
            $table->decimal('sheets', 16, 4)->nullable();
            $table->decimal('cost', 14, 4)->nullable();
            $table->decimal('quantity', 16, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('accident_report_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accident_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['accident_report_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['accident_report_user', 'accident_damages', 'accident_reports', 'bag_schedules', 'daily_reports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
