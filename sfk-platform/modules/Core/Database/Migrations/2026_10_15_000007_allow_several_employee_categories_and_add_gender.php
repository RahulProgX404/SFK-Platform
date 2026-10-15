<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - 従業員カテゴリ becomes multiple choice (user request; legacy APP_02 had a single select): the
 *   `employee_category_user` pivot replaces `users.employee_category_id`, existing values are kept.
 * - 性別 (`users.gender`) for the profile page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_category_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_category_id', 'user_id']);
        });

        $now = now();
        DB::table('users')->whereNotNull('employee_category_id')->orderBy('id')->each(function (object $user) use ($now): void {
            DB::table('employee_category_user')->insert([
                'employee_category_id' => $user->employee_category_id,
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_category_id');
            $table->string('gender', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('gender');
            $table->foreignId('employee_category_id')->nullable()->constrained()->restrictOnDelete();
        });

        // Keep one category per user (the first) when going back to a single select.
        DB::table('employee_category_user')
            ->selectRaw('user_id, min(employee_category_id) as employee_category_id')
            ->groupBy('user_id')
            ->get()
            ->each(fn (object $row) => DB::table('users')->where('id', $row->user_id)->update(['employee_category_id' => $row->employee_category_id]));

        Schema::dropIfExists('employee_category_user');
    }
};
