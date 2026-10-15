<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-style permission management (permission.md):
 * - spaces: groups inside one dashboard (panel id), with member users;
 * - business_apps: the registry of apps (features), each in at most one space, with a lifecycle status;
 * - app_permissions: per app, rows for 全員 / one 従業員カテゴリ / one user with the legacy abilities.
 * Real legacy ACLs were never exported (SYSTEM_BRAIN 15 U-01), so administrators set them here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id', 64)->index();
            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['panel_id', 'name']);
        });

        Schema::create('space_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['space_id', 'user_id']);
        });

        Schema::create('business_apps', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('developing')->index();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('app_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->foreignId('employee_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_export')->default(false);
            $table->timestamps();

            // NULLs are distinct, so the 全員 row (both NULL) is kept unique by AppPermission itself.
            $table->unique(['business_app_id', 'employee_category_id']);
            $table->unique(['business_app_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_permissions');
        Schema::dropIfExists('business_apps');
        Schema::dropIfExists('space_user');
        Schema::dropIfExists('spaces');
    }
};
