<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 承認者 of 16. 申請管理 (user, 2026-11-24): the マネージャー (users.is_manager) the applicant chose on 申請する. That manager
 * (and the app's administrators) decide 承認済 / 差戻し. Blank on the requests of before (the administrators decide them);
 * a deleted user leaves it blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreignId('approver_id')->nullable()->after('worker_id')->constrained('users')->nullOnDelete();
            $table->index('approver_id');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropIndex(['approver_id']);
            $table->dropConstrainedForeignId('approver_id');
        });
    }
};
