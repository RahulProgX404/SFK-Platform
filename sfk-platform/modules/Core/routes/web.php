<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\PasswordController;
use Modules\Core\Http\Controllers\PortalController;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

// Platform entry: the site root opens the dashboard selection portal (guests go to the login page).
Route::redirect('/', '/portal')->name('home');

Route::get('portal', PortalController::class)
    ->middleware(EnsurePasswordIsChanged::class)
    ->name('core.portal');

Route::get('account/password', [PasswordController::class, 'edit'])->name('core.password.edit');
Route::put('account/password', [PasswordController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('core.password.update');
