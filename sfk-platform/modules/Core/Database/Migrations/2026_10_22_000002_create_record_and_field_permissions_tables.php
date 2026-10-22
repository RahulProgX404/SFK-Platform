<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アクセス権 of an app beyond the app permissions (permission.md "Record" / "Field"):
 * - app_record_rules: the レコード rules in priority order, each with its condition (a 絞り込む filter, empty = every
 *   record) and its entries in order — who (作成者 / 個人 / 従業員カテゴリ / 全員) may 閲覧 / 編集 / 削除;
 * - app_field_permissions: the フィールド entries, per field in order — who may 編集 / 閲覧 it, or nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_record_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('filter')->nullable();
            $table->timestamps();
            $table->index(['business_app_id', 'sort_order']);
        });

        Schema::create('app_record_rule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('app_record_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('subject_type', 16);
            $table->foreignId('employee_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
        });

        Schema::create('app_field_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->string('field', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('subject_type', 16);
            $table->foreignId('employee_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('access', 8);
            $table->timestamps();
            $table->index(['business_app_id', 'field', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_field_permissions');
        Schema::dropIfExists('app_record_rule_entries');
        Schema::dropIfExists('app_record_rules');
    }
};
