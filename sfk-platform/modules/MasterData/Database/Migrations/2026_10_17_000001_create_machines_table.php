<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 03. 機械マスタ (SYSTEM_BRAIN APP_03): machines / processes with their seven cost rates, looked up by daily reports
 * (08 / 08N), the bag-making schedule (12) and accident reports (14). 機械コード and 機械名 are unique (APP_03 "MUST be
 * preserved"); 機械名 is the record title and required. The rates are unit prices in yen and may have decimals (legacy
 * decimal scale 4, e.g. ¥0.35 per sheet); amounts derived from them are rounded to whole yen where they are used
 * (Core Yen). `legacy_id` keeps the 機械マスタID (record number) for migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();

            $table->string('code', 64)->nullable()->unique();
            $table->string('name', 64)->unique();

            $table->decimal('processing_rate', 14, 4)->nullable();
            $table->decimal('changeover_rate', 14, 4)->nullable();
            $table->decimal('handwork_rate', 14, 4)->nullable();
            $table->decimal('machine_rate', 14, 4)->nullable();
            $table->decimal('printing_rate', 14, 4)->nullable();
            $table->decimal('plate_change_rate', 14, 4)->nullable();
            $table->decimal('ink_mixing_rate', 14, 4)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
