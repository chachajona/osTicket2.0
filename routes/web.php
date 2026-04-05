<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::prefix('scp')->name('scp.')->group(function () {
    Route::middleware('guest:staff')->group(function () {
        Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
        Route::post('/login', [LoginController::class, 'login']);

        Route::get('/2fa', [TwoFactorController::class, 'show'])->name('2fa');
        Route::post('/2fa', [TwoFactorController::class, 'verify'])->name('2fa.verify');
        Route::post('/2fa/resend', [TwoFactorController::class, 'resend'])->name('2fa.resend');

        Route::get('/password/forgot', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])->name('password.update');
    });

    Route::middleware('auth.staff')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

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
