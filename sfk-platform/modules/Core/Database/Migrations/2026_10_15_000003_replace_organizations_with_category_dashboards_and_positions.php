<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations (組織・役職) are replaced:
 * - 従業員カテゴリ now decides which dashboards (panel ids) a user may open (`employee_categories.dashboards`).
 *   jsonb, not json: PostgreSQL has no equality operator for json, which breaks SELECT DISTINCT.
 * - 役職 (company designation) becomes its own option list (`positions`) with one optional choice per user.
 *   Not in the legacy system (SYSTEM_BRAIN APP_02 has no 役職 field) — a new requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('organizations');

        Schema::table('employee_categories', function (Blueprint $table): void {
            $table->jsonb('dashboards')->nullable();
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_id');
        });
        Schema::dropIfExists('positions');

        Schema::table('employee_categories', function (Blueprint $table): void {
            $table->dropColumn('dashboards');
        });

        // The organization tables as created by 2026_10_14_000002 (data is not restored).
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('code', 32)->nullable()->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('code', 32)->nullable();
            $table->unsignedSmallInteger('rank')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
        });
    }
};
