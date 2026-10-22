<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An app can be placed in several spaces (of one or more dashboards): the space of each app moves from
 * `business_apps.space_id` to the pivot `business_app_space`; the placements made so far are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_app_space', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_app_id', 'space_id']);
        });

        $now = now();
        DB::table('business_apps')->whereNotNull('space_id')->orderBy('id')->get(['id', 'space_id'])
            ->each(fn (object $app) => DB::table('business_app_space')->insert([
                'business_app_id' => $app->id, 'space_id' => $app->space_id, 'created_at' => $now, 'updated_at' => $now,
            ]));

        Schema::table('business_apps', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('space_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_apps', function (Blueprint $table): void {
            $table->foreignId('space_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        // back to one space per app: the first one it was placed in
        DB::table('business_app_space')->orderBy('id')->get(['business_app_id', 'space_id'])
            ->unique('business_app_id')
            ->each(fn (object $row) => DB::table('business_apps')->where('id', $row->business_app_id)->update(['space_id' => $row->space_id]));

        Schema::dropIfExists('business_app_space');
    }
};
