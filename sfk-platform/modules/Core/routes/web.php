<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\LoginController;
use Modules\Core\Http\Controllers\PasswordController;
use Modules\Core\Http\Controllers\PortalController;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

// Platform entry: the site root opens the dashboard selection portal (guests go to the login page).
Route::redirect('/', '/portal')->name('home');

// The single login page of the whole system; dashboards have none of their own.
Route::get('login', [LoginController::class, 'create'])->name('login');
Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('portal', PortalController::class)
    ->middleware(EnsurePasswordIsChanged::class)
    ->name('core.portal');

// Forced password change after the first sign-in / an administrator's reset. Voluntary changes are made on
// the profile page of any dashboard.
Route::middleware('auth')->group(function (): void {
    Route::get('account/password', [PasswordController::class, 'edit'])->name('core.password.edit');
    Route::put('account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('core.password.update');
    Route::post('account/password/keep', [PasswordController::class, 'keep'])->name('core.password.keep');
});
