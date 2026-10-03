<?php

use Illuminate\Support\Facades\Route;

// The site root opens the ERP panel.
Route::redirect('/', '/'.trim((string) config('erp.panel.path', 'erp'), '/'))->name('home');
