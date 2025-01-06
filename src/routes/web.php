<?php

use Illuminate\Support\Facades\Route;
use Feelri\Finder\Http\Controllers\LogController;

Route::get('/logs', [LogController::class, 'index'])->name('finder.logs.index');
Route::get('/logs/view', [LogController::class, 'show'])->name('finder.logs.show');
Route::get('/logs/directory', [LogController::class, 'getSubDirectory'])->name('finder.logs.directory');
