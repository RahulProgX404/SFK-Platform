<?php

use Illuminate\Support\Facades\Route;
use Modules\Production\Http\Controllers\ProductionScheduleScript;

// The script of the 製袋予定表 (board of 12. 製袋予定管理), versioned.
Route::get('production/production-schedule.js', ProductionScheduleScript::class)->name('production.schedule-script');
