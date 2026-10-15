<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-master fields on the platform user (SYSTEM_BRAIN APP_02 従業員マスタ):
 * 担当者コード / 作業員コード (unique, max 64), 部課コード, 作業単価 and 残業単価 (integer yen per hour),
 * and 従業員カテゴリ moved from a fixed enum to the `employee_categories` table so administrators can
 * add options. The legacy options are seeded; 役員 was disabled in the legacy system and starts inactive.
 */
return new class extends Migration
{
    /** Old enum value => legacy label, in the legacy system order. */
    private const CATEGORIES = [
        'sales' => '営業',
        'general_affairs' => '総務',
        'manufacturing' => '製造',
        'engineering' => '工務',
        'design' => '制作',
        'manufacturing_part_time' => '製造パート',
        'sales_part_time' => '営業パート',
        'executive' => '役員',
    ];

    public function up(): void
    {
        Schema::create('employee_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $sort = 0;
        foreach (self::CATEGORIES as $value => $label) {
            DB::table('employee_categories')->insert([
                'name' => $label,
                'sort_order' => ++$sort,
                'is_active' => $value !== 'executive',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('sales_staff_code', 64)->nullable()->unique();
            $table->string('worker_code', 64)->nullable()->unique();
            $table->string('department_code', 64)->nullable();
            $table->foreignId('employee_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('work_rate')->nullable();
            $table->unsignedInteger('overtime_rate')->nullable();
        });

        foreach (self::CATEGORIES as $value => $label) {
            DB::table('users')->where('employee_category', $value)->update([
                'employee_category_id' => DB::table('employee_categories')->where('name', $label)->value('id'),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('employee_category');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_category', 32)->nullable();
        });

        foreach (self::CATEGORIES as $value => $label) {
            $id = DB::table('employee_categories')->where('name', $label)->value('id');
            DB::table('users')->where('employee_category_id', $id)->update(['employee_category' => $value]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['sales_staff_code']);
            $table->dropUnique(['worker_code']);
            $table->dropConstrainedForeignId('employee_category_id');
            $table->dropColumn(['sales_staff_code', 'worker_code', 'department_code', 'work_rate', 'overtime_rate']);
        });

        Schema::dropIfExists('employee_categories');
    }
};
