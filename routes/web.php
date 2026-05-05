<?php

declare(strict_types=1);

use App\Http\Controllers\Account\MigrationBannerController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Account\TwoFactorSecurityController;
use App\Http\Controllers\Account\TwoFactorWizardController;
use App\Http\Controllers\Admin\CannedResponseController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmailConfigController;
use App\Http\Controllers\Admin\FilterController;
use App\Http\Controllers\Admin\HelpTopicController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SlaController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorAppController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Panels\PanelSwitchController;
use App\Http\Controllers\Scp\AttachmentController;
use App\Http\Controllers\Scp\DashboardController;
use App\Http\Controllers\Scp\QueueController;
use App\Http\Controllers\Scp\Queues\QueueColumnsController;
use App\Http\Controllers\Scp\Queues\QueueConfigController;
use App\Http\Controllers\Scp\SearchController;
use App\Http\Controllers\Scp\StaffPreferencesController;
use App\Http\Controllers\Scp\TicketController;
use App\Http\Controllers\Scp\Tickets\AssignmentController;
use App\Http\Controllers\Scp\Tickets\DraftController;
use App\Http\Controllers\Scp\Tickets\LockController;
use App\Http\Controllers\Scp\Tickets\NoteController;
use App\Http\Controllers\Scp\Tickets\StatusController;
use App\Http\Middleware\AuthenticateStaff;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::guard('staff')->check()) {
        return redirect()->route('scp.dashboard');
    }

    return redirect()->route('scp.login');
});

Route::post('/panel/switch', PanelSwitchController::class)->middleware('auth.staff')->name('panel.switch');

Route::prefix('scp')->name('scp.')->group(function () {
    Route::middleware('guest:staff')->group(function () {
        Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
        Route::post('/login', [LoginController::class, 'login']);

        Route::get('/2fa', [TwoFactorController::class, 'show'])->name('2fa');
        Route::post('/2fa', [TwoFactorController::class, 'verify'])->name('2fa.verify');
        Route::post('/2fa/resend', [TwoFactorController::class, 'resend'])->name('2fa.resend');

        Route::get('/2fa-app', [TwoFactorAppController::class, 'show'])->name('2fa-app');
        Route::post('/2fa-app', [TwoFactorAppController::class, 'verify'])->name('2fa-app.verify');

        Route::get('/password/forgot', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])->name('password.update');
    });

    Route::middleware(['auth.staff', 'scp.access', 'scp.log'])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('/account/security', [SecurityController::class, 'show'])->name('account.security');
        Route::get('/account/security/confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
        Route::post('/account/security/confirm-password', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');
        Route::post('/account/migration-banner/dismiss', [MigrationBannerController::class, 'dismiss'])->name('account.migration-banner.dismiss');

        Route::middleware(RequirePassword::using('scp.password.confirm'))->group(function () {
            Route::get('/account/security/two-factor', [TwoFactorWizardController::class, 'show'])->name('account.security.two-factor.show');
            Route::post('/account/security/two-factor/enable', [TwoFactorSecurityController::class, 'enable'])->name('account.security.two-factor.enable');
            Route::post('/account/security/two-factor/confirm', [TwoFactorSecurityController::class, 'confirm'])->name('account.security.two-factor.confirm');
            Route::get('/account/security/two-factor/recovery-codes', [TwoFactorSecurityController::class, 'recoveryCodes'])->name('account.security.two-factor.recovery-codes');
            Route::post('/account/security/two-factor/regenerate-codes', [TwoFactorSecurityController::class, 'regenerateRecoveryCodes'])->name('account.security.two-factor.regenerate-codes');
            Route::delete('/account/security/two-factor', [TwoFactorSecurityController::class, 'disable'])->name('account.security.two-factor.disable');
        });

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
        Route::get('/queues/{queue}', [QueueController::class, 'show'])->name('queues.show');
        Route::get('/queues/{queue}/export', [QueueController::class, 'export'])->name('queues.export');
        Route::patch('/queues/{queue}/columns', [QueueColumnsController::class, 'update'])->name('queues.columns.update');
        Route::patch('/queues/{queue}/config', [QueueConfigController::class, 'update'])->name('queues.config.update');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/lock', [LockController::class, 'acquire'])->name('tickets.lock.acquire');
        Route::post('/tickets/{ticket}/lock/renew', [LockController::class, 'renew'])->name('tickets.lock.renew');
        Route::delete('/tickets/{ticket}/lock', [LockController::class, 'release'])->name('tickets.lock.release');
        Route::post('/tickets/{ticket}/notes', [NoteController::class, 'store'])
            ->middleware('scp.ticket-lock')
            ->name('tickets.notes.store');
        Route::get('/tickets/{ticket}/draft', [DraftController::class, 'show'])->name('tickets.draft.show');
        Route::post('/tickets/{ticket}/draft', [DraftController::class, 'store'])->name('tickets.draft.store');
        Route::patch('/tickets/{ticket}/draft', [DraftController::class, 'update'])->name('tickets.draft.update');
        Route::delete('/tickets/{ticket}/draft', [DraftController::class, 'destroy'])->name('tickets.draft.destroy');
        Route::post('/tickets/{ticket}/assignment', [AssignmentController::class, 'store'])
            ->middleware('scp.ticket-lock')
            ->name('tickets.assignment.store');
        Route::delete('/tickets/{ticket}/assignment', [AssignmentController::class, 'destroy'])
            ->middleware('scp.ticket-lock')
            ->name('tickets.assignment.destroy');
        Route::post('/tickets/{ticket}/status', [StatusController::class, 'store'])
            ->middleware('scp.ticket-lock')
            ->name('tickets.status.store');
        Route::get('/attachments/{file}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('/search', [SearchController::class, 'index'])->name('search');
        Route::get('/preferences', [StaffPreferencesController::class, 'show'])->name('preferences.show');
        Route::patch('/preferences', [StaffPreferencesController::class, 'update'])->name('preferences.update');
    });
});

Route::middleware([AuthenticateStaff::class, 'admin.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('canned-responses', CannedResponseController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('email-config', EmailConfigController::class)->except(['show']);
    Route::resource('help-topics', HelpTopicController::class)->except(['show']);
    Route::resource('filters', FilterController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('slas', SlaController::class)->except(['show']);
    Route::resource('staff', StaffController::class)->except(['show']);
    Route::resource('teams', TeamController::class)->except(['show']);
});

Route::get('/scp/test-auth', function () {
    if (Auth::guard('staff')->check()) {
        $staff = Auth::guard('staff')->user();

        return response()->json([
            'authenticated' => true,
            'staff_id' => $staff->staff_id,
            'name' => $staff->firstname.' '.$staff->lastname,
            'username' => $staff->username,
        ]);
    }

    return response()->json(['authenticated' => false]);
});
