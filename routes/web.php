<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegistrationController;
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

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/verification-notice', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
});
Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->whereNumber('id')->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
Route::get('/email/verified', [EmailVerificationController::class, 'complete'])->name('verification.complete');
