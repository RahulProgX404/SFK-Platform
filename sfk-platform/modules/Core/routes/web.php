<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\DocumentController;
use Modules\Core\Http\Controllers\LoginController;
use Modules\Core\Http\Controllers\PasswordController;
use Modules\Core\Http\Controllers\PortalController;
use Modules\Core\Http\Controllers\ThemeAssetController;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

// The panels' stylesheet and script (versioned, kept by the browser).
Route::get('core/theme.css', [ThemeAssetController::class, 'css'])->name('core.theme.css');
Route::get('core/theme.js', [ThemeAssetController::class, 'js'])->name('core.theme.js');

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

// 帳票出力 of the apps (PDF files; the app screens put the buttons and settings around them).
// Making PDFs is heavy: at most 30 files a minute per user.
Route::middleware(['auth', EnsurePasswordIsChanged::class, 'throttle:30,1'])->prefix('documents')->name('core.documents.')->group(function (): void {
    Route::get('templates/{template}/background', [DocumentController::class, 'background'])->name('background');
    Route::match(['get', 'post'], 'templates/{template}/preview', [DocumentController::class, 'preview'])->name('preview');
    Route::get('outputs/{output}/records/{record}', [DocumentController::class, 'record'])->name('record');
    Route::get('print/{token}', [DocumentController::class, 'batch'])->name('print');
});
