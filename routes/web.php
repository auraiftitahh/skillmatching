<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\LayoutPreviewController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'entry'])->name('home');

Route::prefix('preview')->name('preview.')->group(function () {
    Route::get('/', [LayoutPreviewController::class, 'index'])->name('index');
    Route::get('/auth/{page}', [LayoutPreviewController::class, 'authentication'])->name('auth');
    Route::get('/komponen', [LayoutPreviewController::class, 'components'])->name('components');
    Route::get('/{role}/{page?}', [LayoutPreviewController::class, 'workspace'])->name('workspace');
});

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'resetForm'])->middleware('throttle:20,1')->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/verification-notice', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
});
Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->whereNumber('id')->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
Route::get('/email/verified', [EmailVerificationController::class, 'complete'])->name('verification.complete');

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'account.eligible', 'auth.session'])->group(function () {
    Route::get('/sertifikasi/menunggu-verifikasi', [WorkspaceController::class, 'institutionStatus'])->middleware('workspace.role:sertifikasi')->name('institution.status');
    Route::get('/{role}/{page?}', [WorkspaceController::class, 'show'])
        ->whereIn('role', ['pelamar', 'admin', 'sertifikasi'])
        ->middleware(['workspace.role', 'institution.active'])->name('workspace');
});
