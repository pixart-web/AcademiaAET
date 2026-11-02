<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\Auth\ChildSessionController;
use App\Http\Controllers\ChildFeedbackController;
use App\Http\Controllers\ChildHomeController;
use App\Http\Controllers\ChildProfileController;
use App\Http\Controllers\ConsentRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceAssociationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\GuardianHomeController;
use App\Http\Controllers\GuardianRelationshipController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\MediaStreamController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfessionalAssignmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\MfaSettingsController;
use App\Http\Controllers\Settings\SessionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// ---------------------------------------------------------------------------
// Professional portal (admin + professional) — role checked on the server via
// the `role` middleware and Policies, never only by hiding a button.
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin,professional'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('staff', UserController::class)->except(['show'])->parameters(['staff' => 'user']);
    Route::patch('staff/{user}/reactivate', [UserController::class, 'reactivate'])->name('staff.reactivate');
    Route::post('staff/{user}/resend-activation', [UserController::class, 'resendActivation'])->name('staff.resend-activation');

    Route::resource('children', ChildProfileController::class);
    Route::post('children/{child}/professionals', [ProfessionalAssignmentController::class, 'store'])->name('children.professionals.store');
    Route::delete('children/{child}/professionals/{assignment}', [ProfessionalAssignmentController::class, 'destroy'])->name('children.professionals.destroy');
    Route::post('children/{child}/guardians', [GuardianRelationshipController::class, 'store'])->name('children.guardians.store');
    Route::delete('children/{child}/guardians/{relationship}', [GuardianRelationshipController::class, 'destroy'])->name('children.guardians.destroy');
    Route::post('children/{child}/consents', [ConsentRecordController::class, 'store'])->name('children.consents.store');
    Route::patch('children/{child}/consents/{consent}/revoke', [ConsentRecordController::class, 'revoke'])->name('children.consents.revoke');
    Route::post('children/{child}/assignments', [AssignmentController::class, 'store'])->name('children.assignments.store');
    Route::post('children/{child}/devices', [DeviceAssociationController::class, 'store'])->name('children.devices.store');
    Route::patch('children/{child}/devices/{device}/revoke', [DeviceAssociationController::class, 'revoke'])->name('children.devices.revoke');
    Route::get('children/{child}/export', [ChildProfileController::class, 'export'])->name('children.export');
    Route::delete('children/{child}/erase', [ChildProfileController::class, 'eraseCompletely'])->name('children.erase');

    Route::delete('assignments/{assignment}', [AssignmentController::class, 'cancel'])->name('assignments.cancel');

    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/create', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::post('activities/{activity}/publish', [ActivityController::class, 'publish'])->name('activities.publish');
    Route::post('activities/{activity}/archive', [ActivityController::class, 'archive'])->name('activities.archive');
    Route::post('activities/{activity}/duplicate', [ActivityController::class, 'duplicate'])->name('activities.duplicate');
    Route::get('activities/{activity}/preview', [ActivityController::class, 'preview'])->name('activities.preview');
    Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::get('media', [MediaAssetController::class, 'index'])->name('media.index');
    Route::post('media', [MediaAssetController::class, 'store'])->name('media.store');
    Route::delete('media/{media}', [MediaAssetController::class, 'destroy'])->name('media.destroy');

    Route::get('evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
    Route::get('evaluations/{attempt}', [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::post('evaluations/{attempt}', [EvaluationController::class, 'store'])->name('evaluations.store');

    Route::get('audit', [AuditEventController::class, 'index'])->name('audit.index');
    Route::get('pesquisa', SearchController::class)->name('search');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/settings/mfa', [MfaSettingsController::class, 'edit'])->name('mfa.edit');
    Route::post('/settings/mfa', [MfaSettingsController::class, 'enable'])->name('mfa.enable');
    Route::delete('/settings/mfa', [MfaSettingsController::class, 'disable'])->name('mfa.disable');

    Route::get('/settings/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('/settings/sessions/{sessionId}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

// ---------------------------------------------------------------------------
// Guardian placeholder (portal itself out of scope this stage)
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:guardian'])->get('/encarregado', GuardianHomeController::class)->name('guardian.home');

// ---------------------------------------------------------------------------
// Private media, streamed only via short-lived signed URLs
// ---------------------------------------------------------------------------
Route::get('/media/{media}/file', MediaStreamController::class)->name('media.show');

// ---------------------------------------------------------------------------
// Child portal — separate "child" guard, no email/password, device+PIN only.
// ---------------------------------------------------------------------------
Route::prefix('crianca')->group(function () {
    Route::middleware('guest:child')->group(function () {
        Route::get('entrar', [ChildSessionController::class, 'create'])->name('child.login');
        Route::post('entrar/ativar', [ChildSessionController::class, 'activate'])
            ->middleware('throttle:10,1')->name('child.activate');
        Route::post('entrar/pin', [ChildSessionController::class, 'unlock'])
            ->middleware('throttle:10,1')->name('child.unlock');
    });

    Route::middleware('auth:child')->group(function () {
        Route::get('/', ChildHomeController::class)->name('child.home');
        Route::get('atribuicoes/{assignment}/feedback', ChildFeedbackController::class)->name('child.feedback');

        Route::post('atribuicoes/{assignment}/iniciar', [AttemptController::class, 'start'])->name('child.assignments.start');
        Route::get('tentativas/{attempt}', [AttemptController::class, 'show'])->name('child.attempts.show');
        Route::post('tentativas/{attempt}/passos/{step}', [AttemptController::class, 'saveStep'])->name('child.attempts.save-step');
        Route::post('tentativas/{attempt}/submeter', [AttemptController::class, 'submit'])->name('child.attempts.submit');

        Route::post('sair', [ChildSessionController::class, 'destroy'])->name('child.logout');
    });
});

require __DIR__.'/auth.php';
