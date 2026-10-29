<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\InstitutionReviewController;
use App\Http\Controllers\Admin\JobPublicationController;
use App\Http\Controllers\Admin\JobTaxonomyController;
use App\Http\Controllers\Admin\JobVacancyController;
use App\Http\Controllers\Admin\SkillCatalogController;
use App\Http\Controllers\Admin\SkillRelationController;
use App\Http\Controllers\Admin\TrainingPublicationController;
use App\Http\Controllers\Admin\TrainingReviewController;
use App\Http\Controllers\Admin\WeightConfigurationController;
use App\Http\Controllers\Applicant\ExperienceController;
use App\Http\Controllers\Applicant\JobCatalogController;
use App\Http\Controllers\Applicant\MatchingController;
use App\Http\Controllers\Applicant\ProfileController;
use App\Http\Controllers\Applicant\SkillController;
use App\Http\Controllers\Applicant\SkillSuggestionController;
use App\Http\Controllers\Applicant\TrainingCatalogController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Institution\TrainingSubmissionController;
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
    Route::prefix('pelamar')->name('applicant.')->middleware('workspace.role:pelamar')->group(function () {
        Route::get('/skill/saran', [SkillSuggestionController::class, 'index'])->name('skills.suggestions');
        Route::get('/skill', [SkillController::class, 'edit'])->name('skills.edit');
        Route::put('/skill', [SkillController::class, 'update'])->name('skills.update');
        Route::get('/pelatihan', [TrainingCatalogController::class, 'index'])->name('trainings.index');
        Route::get('/pelatihan/{training}', [TrainingCatalogController::class, 'show'])->whereNumber('training')->name('trainings.show');
        Route::get('/lowongan', [JobCatalogController::class, 'index'])->name('jobs.index');
        Route::get('/lowongan/{job}', [JobCatalogController::class, 'show'])->whereNumber('job')->name('jobs.show');
        Route::post('/lowongan/{job}/simpan', [JobCatalogController::class, 'save'])->whereNumber('job')->name('jobs.save');
        Route::delete('/lowongan/{job}/simpan', [JobCatalogController::class, 'unsave'])->whereNumber('job')->name('jobs.unsave');
        Route::get('/kecocokan', [MatchingController::class, 'index'])->name('matching.index');
        Route::get('/lowongan/{job}/kecocokan', [MatchingController::class, 'show'])->whereNumber('job')->name('matching.show');
        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/riwayat', [ExperienceController::class, 'index'])->name('experiences.index');
        Route::get('/riwayat/tambah', [ExperienceController::class, 'create'])->name('experiences.create');
        Route::post('/riwayat', [ExperienceController::class, 'store'])->name('experiences.store');
        Route::get('/riwayat/{experience}/ubah', [ExperienceController::class, 'edit'])->whereNumber('experience')->name('experiences.edit');
        Route::put('/riwayat/{experience}', [ExperienceController::class, 'update'])->whereNumber('experience')->name('experiences.update');
        Route::delete('/riwayat/{experience}', [ExperienceController::class, 'destroy'])->whereNumber('experience')->name('experiences.destroy');
    });
    Route::prefix('admin')->name('admin.')->middleware('workspace.role:admin')->group(function () {
        Route::get('/jabatan', [JobTaxonomyController::class, 'index'])->name('taxonomies.index');
        Route::get('/jabatan/tambah', [JobTaxonomyController::class, 'create'])->name('taxonomies.create');
        Route::post('/jabatan', [JobTaxonomyController::class, 'store'])->name('taxonomies.store');
        Route::get('/jabatan/{taxonomy}', [JobTaxonomyController::class, 'edit'])->whereNumber('taxonomy')->name('taxonomies.edit');
        Route::put('/jabatan/{taxonomy}', [JobTaxonomyController::class, 'update'])->whereNumber('taxonomy')->name('taxonomies.update');
        Route::get('/pelatihan', [TrainingPublicationController::class, 'index'])->name('trainings.index');
        Route::get('/pelatihan/{training}/publikasi', [TrainingPublicationController::class, 'edit'])->whereNumber('training')->name('trainings.publication');
        Route::put('/pelatihan/{training}/prasyarat', [TrainingPublicationController::class, 'update'])->whereNumber('training')->name('trainings.prerequisites');
        Route::patch('/pelatihan/{training}/publikasi', [TrainingPublicationController::class, 'status'])->whereNumber('training')->name('trainings.status');
        Route::patch('/lowongan/{job}/publikasi', [JobPublicationController::class, 'update'])->whereNumber('job')->name('jobs.publication');
        Route::get('/lowongan', [JobVacancyController::class, 'index'])->name('jobs.index');
        Route::get('/lowongan/tambah', [JobVacancyController::class, 'create'])->name('jobs.create');
        Route::post('/lowongan', [JobVacancyController::class, 'store'])->name('jobs.store');
        Route::get('/lowongan/{job}', [JobVacancyController::class, 'edit'])->whereNumber('job')->name('jobs.edit');
        Route::put('/lowongan/{job}', [JobVacancyController::class, 'update'])->whereNumber('job')->name('jobs.update');
        Route::get('/bobot/riwayat', [WeightConfigurationController::class, 'history'])->name('weights.history');
        Route::get('/bobot/versi/{configuration}', [WeightConfigurationController::class, 'show'])->whereNumber('configuration')->name('weights.show');
        Route::get('/bobot', [WeightConfigurationController::class, 'edit'])->name('weights.edit');
        Route::post('/bobot', [WeightConfigurationController::class, 'store'])->name('weights.store');
        Route::get('/persetujuan-lembaga', [InstitutionReviewController::class, 'index'])->name('institutions.index');
        Route::get('/persetujuan-lembaga/{body}', [InstitutionReviewController::class, 'show'])->whereNumber('body')->name('institutions.show');
        Route::post('/persetujuan-lembaga/{body}/keputusan', [InstitutionReviewController::class, 'decide'])->whereNumber('body')->name('institutions.decide');
        Route::get('/pengajuan-pelatihan', [TrainingReviewController::class, 'index'])->name('training-reviews.index');
        Route::get('/pengajuan-pelatihan/{submission}', [TrainingReviewController::class, 'show'])->whereNumber('submission')->name('training-reviews.show');
        Route::post('/pengajuan-pelatihan/{submission}/keputusan', [TrainingReviewController::class, 'decide'])->whereNumber('submission')->name('training-reviews.decide');
        Route::get('/skill/{skill}/relasi', [SkillRelationController::class, 'index'])->whereNumber('skill')->name('skills.relations');
        Route::post('/skill/{skill}/relasi', [SkillRelationController::class, 'store'])->whereNumber('skill')->name('skills.relations.store');
        Route::delete('/skill/{skill}/relasi/{relation}', [SkillRelationController::class, 'destroy'])->whereNumber(['skill', 'relation'])->name('skills.relations.destroy');
        Route::get('/skill', [SkillCatalogController::class, 'index'])->name('skills.index');
        Route::get('/skill/{skill}', [SkillCatalogController::class, 'edit'])->whereNumber('skill')->name('skills.edit');
        Route::patch('/skill/{skill}', [SkillCatalogController::class, 'update'])->whereNumber('skill')->name('skills.update');
        Route::get('/akun', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/akun/{user}', [AccountController::class, 'show'])->whereNumber('user')->name('accounts.show');
        Route::patch('/akun/{user}/status', [AccountController::class, 'updateStatus'])->whereNumber('user')->name('accounts.status');
    });
    Route::prefix('sertifikasi')->name('institution.')->middleware(['workspace.role:sertifikasi', 'institution.active'])->group(function () {
        Route::get('/pelatihan', [TrainingSubmissionController::class, 'published'])->name('trainings.index');
        Route::post('/pelatihan/{training}/revisi', [TrainingSubmissionController::class, 'revise'])->whereNumber('training')->name('trainings.revise');
        Route::get('/pengajuan', [TrainingSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/pengajuan/tambah', [TrainingSubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/pengajuan', [TrainingSubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/pengajuan/{submission}', [TrainingSubmissionController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::get('/pengajuan/{submission}/ubah', [TrainingSubmissionController::class, 'edit'])->whereNumber('submission')->name('submissions.edit');
        Route::post('/pengajuan/{submission}/kirim', [TrainingSubmissionController::class, 'send'])->whereNumber('submission')->name('submissions.send');
        Route::put('/pengajuan/{submission}', [TrainingSubmissionController::class, 'update'])->whereNumber('submission')->name('submissions.update');
    });
    Route::get('/sertifikasi/status-akun', [WorkspaceController::class, 'institutionStatus'])->middleware('workspace.role:sertifikasi')->name('institution.account-status');
    Route::get('/sertifikasi/menunggu-verifikasi', [WorkspaceController::class, 'institutionStatus'])->middleware('workspace.role:sertifikasi')->name('institution.status');
    Route::get('/{role}/{page?}', [WorkspaceController::class, 'show'])
        ->whereIn('role', ['pelamar', 'admin', 'sertifikasi'])
        ->middleware(['workspace.role', 'institution.active'])->name('workspace');
});
