<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    private const RESET_LINK_THROTTLE_SECONDS = 60;

    private const RESET_TOKEN_LENGTH = 64;

    public function showForgotForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = trim((string) $request->input('email'));
        $normalizedEmail = Str::lower($email);
        $throttleKey = sprintf('password-reset.%s.%s', $normalizedEmail, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Please wait {$seconds} seconds before requesting another reset link.",
            ]);
        }

        RateLimiter::hit($throttleKey, self::RESET_LINK_THROTTLE_SECONDS);

        $staff = Staff::whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('isactive', 1)
            ->first();

        if ($staff) {
            $token = $this->issueResetToken($staff);

            if ($token !== null) {
                Mail::to($staff->email)->queue(
                    new PasswordResetLinkMail(route('scp.password.reset', ['token' => $token]))
                );
            }
        }

        return back()->with('status', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetForm(Request $request, string $token): Response|RedirectResponse
    {
        if (! $this->isValidResetToken($token)) {
            return redirect()->route('scp.password.request')->withErrors([
                'general' => 'This password reset link is invalid or has expired.',
            ]);
        }

        $staffId = Cache::get("password_reset.{$token}");

        if (! $staffId) {
            return redirect()->route('scp.password.request')->withErrors([
                'general' => 'This password reset link is invalid or has expired.',
            ]);
        }

        return Inertia::render('Auth/ResetPassword', ['token' => $token]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{'.self::RESET_TOKEN_LENGTH.'}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $token = (string) $request->input('token');
        $staffId = Cache::pull("password_reset.{$token}");

        if (! $staffId) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        Cache::forget("password_reset_staff.{$staffId}");

        $staff = Staff::where('staff_id', $staffId)
            ->where('isactive', 1)
            ->first();

        if (! $staff) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $staff->passwd = Hash::make($request->input('password'));
        $staff->save();

        return redirect()->route('scp.login')->with('status', 'Password reset successfully. Please log in.');
    }

    private function issueResetToken(Staff $staff): ?string
    {
        $result = Cache::lock("password_reset_staff_lock.{$staff->staff_id}", 5)->get(function () use ($staff): string {
            $previousToken = Cache::get("password_reset_staff.{$staff->staff_id}");

            if (is_string($previousToken) && $previousToken !== '') {
                Cache::forget("password_reset.{$previousToken}");
            }

            $token = Str::random(self::RESET_TOKEN_LENGTH);

            Cache::put("password_reset.{$token}", $staff->staff_id, now()->addMinutes(60));
            Cache::put("password_reset_staff.{$staff->staff_id}", $token, now()->addMinutes(60));

            return $token;
        });

        return is_string($result) && $result !== '' ? $result : null;
    }

    private function isValidResetToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{'.self::RESET_TOKEN_LENGTH.'}$/', $token) === 1;
    }
}
