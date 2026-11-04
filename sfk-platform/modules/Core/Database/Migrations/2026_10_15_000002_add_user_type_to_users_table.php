<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザー種別 (`user_type`: super_admin / admin / employee) — how a user uses the system. On databases that still
 * have the former role tables, the `super-admin` role and the `employee-master.manage` permission are taken over
 * (the tables themselves are dropped by 2026_11_04_000001_drop_role_and_activity_log_tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_type', 32)->default('employee')->index();
        });

        if (Schema::hasTable('model_has_permissions') && Schema::hasTable('permissions')) {
            DB::table('users')->whereIn('id', $this->grantedUserIds('model_has_permissions', 'permissions', 'permission_id', 'employee-master.manage'))
                ->update(['user_type' => 'admin']);
        }

        $superAdmins = Schema::hasTable('model_has_roles') && Schema::hasTable('roles')
            ? $this->grantedUserIds('model_has_roles', 'roles', 'role_id', 'super-admin')
            : [];
        DB::table('users')
            ->where(fn ($query) => $query->whereIn('id', $superAdmins)->orWhere('managed_by_env', true))
            ->update(['user_type' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['user_type']);
            $table->dropColumn('user_type');
        });
    }

    /** @return list<int> */
    private function grantedUserIds(string $pivot, string $table, string $key, string $name): array
    {
        return DB::table($pivot.' as pivot')
            ->join($table.' as granted', 'granted.id', '=', 'pivot.'.$key)
            ->where('granted.name', $name)
            ->where('pivot.model_type', User::class)
            ->pluck('pivot.model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
};
