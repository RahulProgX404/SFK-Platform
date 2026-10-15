<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * ユーザー種別 (`user_type`: super_admin / admin / employee) — how a user uses the system — replaces the
 * `super-admin` role and the `employee-master.manage` permission. Roles are left to mean only the
 * person's position/duty in the company.
 */
return new class extends Migration
{
    private const SUPER_ADMIN_ROLE = 'super-admin';

    private const ADMIN_PERMISSION = 'employee-master.manage';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_type', 32)->default('employee')->index();
        });

        $roleUsers = $this->roleUserIds();
        DB::table('users')->whereIn('id', $this->permissionUserIds())->update(['user_type' => 'admin']);
        DB::table('users')
            ->where(fn ($query) => $query->whereIn('id', $roleUsers)->orWhere('managed_by_env', true))
            ->update(['user_type' => 'super_admin']);

        DB::table($this->table('roles'))->where('name', self::SUPER_ADMIN_ROLE)->delete();
        DB::table($this->table('permissions'))->where('name', self::ADMIN_PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $now = now();
        $roleId = DB::table($this->table('roles'))->insertGetId(
            ['name' => self::SUPER_ADMIN_ROLE, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        );
        $permissionId = DB::table($this->table('permissions'))->insertGetId(
            ['name' => self::ADMIN_PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        );

        foreach (DB::table('users')->where('user_type', 'super_admin')->pluck('id') as $id) {
            DB::table($this->table('model_has_roles'))->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $id]);
        }
        foreach (DB::table('users')->where('user_type', 'admin')->pluck('id') as $id) {
            DB::table($this->table('model_has_permissions'))->insert(['permission_id' => $permissionId, 'model_type' => User::class, 'model_id' => $id]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['user_type']);
            $table->dropColumn('user_type');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<int> */
    private function roleUserIds(): array
    {
        return DB::table($this->table('model_has_roles').' as pivot')
            ->join($this->table('roles').' as roles', 'roles.id', '=', 'pivot.role_id')
            ->where('roles.name', self::SUPER_ADMIN_ROLE)
            ->where('pivot.model_type', User::class)
            ->pluck('pivot.model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function permissionUserIds(): array
    {
        return DB::table($this->table('model_has_permissions').' as pivot')
            ->join($this->table('permissions').' as permissions', 'permissions.id', '=', 'pivot.permission_id')
            ->where('permissions.name', self::ADMIN_PERMISSION)
            ->where('pivot.model_type', User::class)
            ->pluck('pivot.model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }
};
