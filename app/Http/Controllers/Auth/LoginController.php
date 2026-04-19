<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\TwoFactorAuthService;
use App\Services\TwoFactorAppChallengeService;
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
    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly TwoFactorAppChallengeService $appChallenge,
    ) {}

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
        $this->ensureDashboardIsTheFallbackIntendedUrl($request);

        if ($staff->hasTotpEnabled()) {
            $this->twoFactor->clearToken($staff->staff_id);
            $this->appChallenge->begin($staff->staff_id);
            $request->session()->put('2fa_app.staff_id', $staff->staff_id);
            $request->session()->put('2fa_app.remember', $request->boolean('remember'));

            return redirect()->route('scp.2fa-app');
        }

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
            $this->appChallenge->clear($staff->staff_id);
        }

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('scp.login');
    }

    private function ensureDashboardIsTheFallbackIntendedUrl(Request $request): void
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || rtrim($intended, '/') === rtrim(url('/'), '/')) {
            $request->session()->put('url.intended', route('scp.dashboard'));
        }
    }
}
