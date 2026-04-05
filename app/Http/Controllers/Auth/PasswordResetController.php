<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function showForgotForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $staff = Staff::where('email', $request->input('email'))
            ->where('isactive', 1)
            ->first();

        if ($staff) {
            $token = Str::random(64);

            \Illuminate\Support\Facades\Cache::put(
                "password_reset.{$token}",
                $staff->staff_id,
                now()->addMinutes(60)
            );

            $resetUrl = route('scp.password.reset', ['token' => $token]);

            Mail::raw(
                "Reset your osTicket password:\n\n{$resetUrl}\n\nThis link expires in 60 minutes.",
                fn ($message) => $message
                    ->to($staff->email)
                    ->subject('Reset Your Password')
            );
        }

        return back()->with('status', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetForm(Request $request, string $token): Response|RedirectResponse
    {
        $staffId = \Illuminate\Support\Facades\Cache::get("password_reset.{$token}");

        if (! $staffId) {
            return redirect()->route('scp.password.request')->withErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);
        }

        return Inertia::render('Auth/ResetPassword', ['token' => $token]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $token = $request->input('token');
        $staffId = \Illuminate\Support\Facades\Cache::get("password_reset.{$token}");

        if (! $staffId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $staff = Staff::find($staffId);

        if (! $staff) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'token' => ['Staff account not found.'],
            ]);
        }

        $staff->passwd = Hash::make($request->input('password'));
        $staff->save();

        \Illuminate\Support\Facades\Cache::forget("password_reset.{$token}");

        return redirect()->route('scp.login')->with('status', 'Password reset successfully. Please log in.');
    }
}
