<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 通知設定 of the users (プロフィール): per kind of 通知 受け取る / メール (Modules\Core\Models\NotificationSetting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('kind', 100);
            $table->boolean('receive')->default(true);
            $table->boolean('mail')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
