<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * スペースの権限 (docs/permissions.md "Space"): every space carries one permission setting — rows for 全員 / one
 * 従業員カテゴリ / one member × 閲覧・追加・編集・削除・ファイル読み込み・ファイル書き出し, like app_permissions — that
 * applies to its members in every app of the space, added to the apps' own permissions (AppAccess). A new space has
 * no rows, so existing access does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->index()->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->foreignId('employee_category_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_export')->default(false);
            $table->timestamps();

            // NULLs are distinct, so the 全員 row (both NULL) is kept unique by SpacePermission itself.
            $table->unique(['space_id', 'employee_category_id']);
            $table->unique(['space_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_permissions');
    }
};
