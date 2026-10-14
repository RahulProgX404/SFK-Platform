<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee profile on the platform user (SYSTEM_BRAIN APP_02 従業員マスタ: category, 部課, 在籍状況)
 * plus the account flags used by the forced password change and the env-managed super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('last_name', 64)->nullable()->after('name');
            $table->string('first_name', 64)->nullable()->after('last_name');
            $table->string('last_name_kana', 64)->nullable()->after('first_name');
            $table->string('first_name_kana', 64)->nullable()->after('last_name_kana');
            $table->string('employee_code', 32)->nullable()->unique()->after('first_name_kana');
            $table->string('employee_category', 32)->nullable()->after('employee_code');
            $table->string('employment_status', 32)->default('active')->index()->after('employee_category');
            $table->date('birthday')->nullable();
            $table->date('hired_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('emergency_contact_name', 64)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('managed_by_env')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['employee_code']);
            $table->dropIndex(['employment_status']);
            $table->dropColumn([
                'last_name', 'first_name', 'last_name_kana', 'first_name_kana', 'employee_code',
                'employee_category', 'employment_status', 'birthday', 'hired_on', 'left_on',
                'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
                'emergency_contact_name', 'emergency_contact_phone', 'notes',
                'must_change_password', 'password_changed_at', 'last_login_at', 'managed_by_env',
            ]);
        });
    }
};
