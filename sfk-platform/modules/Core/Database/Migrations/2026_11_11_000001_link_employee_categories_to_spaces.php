<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members of a スペース by 従業員カテゴリ (user, 2026-11-11): a space may hold categories; every employee of such a
 * category is a member, also those who get the category later, and leaves when it is taken off (SpaceMembership).
 * `space_user` stays the list of all members (AppAccess, the sidebar read it); `direct` tells who was added by name —
 * they stay when their category goes. Existing members were all added by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_category_space', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_category_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['space_id', 'employee_category_id']);
        });

        Schema::table('space_user', function (Blueprint $table): void {
            $table->boolean('direct')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('space_user', function (Blueprint $table): void {
            $table->dropColumn('direct');
        });
        Schema::dropIfExists('employee_category_space');
    }
};
