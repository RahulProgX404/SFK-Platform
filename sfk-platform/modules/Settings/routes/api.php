<?php

use App\Modules\ModuleManager;
use Illuminate\Support\Facades\Route;

/*
| Public API of the Settings extension — mounted at /api/settings/…, route names "settings.api.*".
| Protect real endpoints with authentication (e.g. Laravel Sanctum tokens) before exposing data.
*/

Route::get('status', fn (ModuleManager $modules) => response()->json([
    'module' => 'settings',
    'version' => $modules->get('settings')->version,
    'status' => 'ok',
]))->name('status');
