<?php

use App\Http\Controllers\LayoutPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('preview')->name('preview.')->group(function () {
    Route::get('/', [LayoutPreviewController::class, 'index'])->name('index');
    Route::get('/auth/{page}', [LayoutPreviewController::class, 'authentication'])->name('auth');
    Route::get('/komponen', [LayoutPreviewController::class, 'components'])->name('components');
    Route::get('/{role}/{page?}', [LayoutPreviewController::class, 'workspace'])->name('workspace');
});
