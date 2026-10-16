<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 変更履歴 of app records (legacy record history): one row per version of a record with the record's content at that
 * time (snapshot), the fields changed against the previous version, who saved it and how (created / updated /
 * restored). Versions are numbered per record from 1 (the initial version).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type', 128);
            $table->unsignedBigInteger('record_id');
            $table->unsignedInteger('version');
            $table->string('event', 16);
            $table->jsonb('snapshot');
            $table->jsonb('changed');
            $table->unsignedInteger('restored_from')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['record_type', 'record_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_versions');
    }
};
