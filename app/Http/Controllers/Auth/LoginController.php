<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactor) {}

    public function showLogin(): Response
    {
        if (Auth::guard('staff')->check()) {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login.' . $request->input('username') . '.' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $staff = Staff::where('username', $credentials['username'])
            ->where('isactive', 1)
            ->first();

        if (! $staff || ! Auth::guard('staff')->validate($credentials)) {
            RateLimiter::hit($throttleKey, 300);
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $staff->rehashPasswordIfNeeded($credentials['password']);

        $code = $this->twoFactor->generateToken($staff->staff_id);

        Mail::raw(
            "Your osTicket login code is: {$code}\n\nThis code expires in 6 minutes.",
            fn ($message) => $message
                ->to($staff->email)
                ->subject('Your Login Verification Code')
        );

        $request->session()->put('2fa.staff_id', $staff->staff_id);
        $request->session()->put('2fa.remember', $request->boolean('remember'));

        return redirect()->route('scp.2fa');
    }

    public function logout(Request $request): RedirectResponse
    {
        $staff = Auth::guard('staff')->user();

        if ($staff) {
            $this->twoFactor->clearToken($staff->staff_id);
        }

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('scp.login');
    }
}
