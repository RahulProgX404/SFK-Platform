<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コメント of app records (legacy record comments): who wrote what and when, and the users it was addressed to
 * (宛先), who are notified with the app's own comment recipients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type', 128);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->jsonb('mentions');
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_comments');
    }
};
