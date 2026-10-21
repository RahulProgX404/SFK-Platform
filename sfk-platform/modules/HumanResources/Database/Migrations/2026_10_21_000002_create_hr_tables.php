<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR & safety (SYSTEM_BRAIN 16 申請管理, 17 安全衛生チェック, 18 アルコールチェック).
 *
 * - `leave_requests`: attendance requests with the legacy approval 申請前 → 第一申請中 → 承認済 (差戻し back). 申請者 is a
 *   user, 作業員名 an employee (both users here; the legacy lookup of 02).
 * - `safety_checks` / `safety_check_items`: a person's checklist of a day with its rows (No, 項目, 評価, チェック, 備考).
 * - `alcohol_checks`: the check before and after driving of a driver and day (検査日), each block with its time, vehicle,
 *   method, result, confirmer, instruction and notes. Records are kept (legal retention of one year).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('request_type', 16);
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 32);
            $table->date('target_on');
            $table->string('total_time', 8)->nullable();
            $table->string('clock_time', 8)->nullable();
            $table->text('reason');
            $table->string('status', 16)->default('申請前');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['applicant_id', 'target_on']);
        });

        Schema::create('safety_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('department_machine')->nullable();
            $table->string('department', 16);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('safety_check_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('safety_check_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->decimal('number', 8, 2)->nullable();
            $table->text('item')->nullable();
            $table->string('answer', 8)->default('はい');
            $table->boolean('checked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('alcohol_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->date('checked_on');
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            foreach (['before', 'after'] as $block) {
                $table->string($block.'_time', 8)->nullable();
                $table->string($block.'_vehicle', 32)->nullable();
                $table->string($block.'_method', 16)->nullable();
                $table->string($block.'_detected', 4)->nullable();
                $table->foreignId($block.'_checker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string($block.'_instruction')->nullable();
                $table->text($block.'_notes')->nullable();
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        foreach (['alcohol_checks', 'safety_check_items', 'safety_checks', 'leave_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
