<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The role / permission tables and the activity log of the removed packages (spatie/laravel-permission,
 * spatie/laravel-activitylog). System rights are the ユーザー種別 (`user_type`), app rights AppPermission and record
 * history RecordsHistory, so the tables are empty and unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'activity_log'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
