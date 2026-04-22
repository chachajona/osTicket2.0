<?php

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Account\TwoFactorSecurityController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorAppController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::guard('staff')->check()) {
        return redirect()->route('scp.dashboard');
    }

    return redirect()->route('scp.login');
});

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

    Route::middleware('auth.staff')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('/account/security', [SecurityController::class, 'show'])->name('account.security');
        Route::get('/account/security/confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
        Route::post('/account/security/confirm-password', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');

        Route::middleware(RequirePassword::using('scp.password.confirm'))->group(function () {
            Route::post('/account/security/two-factor/enable', [TwoFactorSecurityController::class, 'enable'])->name('account.security.two-factor.enable');
            Route::post('/account/security/two-factor/confirm', [TwoFactorSecurityController::class, 'confirm'])->name('account.security.two-factor.confirm');
            Route::get('/account/security/two-factor/recovery-codes', [TwoFactorSecurityController::class, 'recoveryCodes'])->name('account.security.two-factor.recovery-codes');
            Route::get('/account/security/two-factor/qr-code', [TwoFactorSecurityController::class, 'qrCode'])->name('account.security.two-factor.qr-code');
            Route::post('/account/security/two-factor/regenerate-codes', [TwoFactorSecurityController::class, 'regenerateRecoveryCodes'])->name('account.security.two-factor.regenerate-codes');
            Route::delete('/account/security/two-factor', [TwoFactorSecurityController::class, 'disable'])->name('account.security.two-factor.disable');
        });

        Route::get('/', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');
    });
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
