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

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactor) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa.staff_id')) {
            return redirect()->route('scp.login');
        }

        return Inertia::render('Auth/TwoFactor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $staffId = $request->session()->get('2fa.staff_id');

        if (! $staffId) {
            return redirect()->route('scp.login');
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $status = $this->twoFactor->validateTokenState((int) $staffId, $request->input('code'));

        if ($status !== TwoFactorAuthService::STATUS_VALID) {
            if (in_array($status, [TwoFactorAuthService::STATUS_LOCKED_OUT, TwoFactorAuthService::STATUS_EXPIRED], true)) {
                $request->session()->forget(['2fa.staff_id', '2fa.remember']);

                return redirect()->route('scp.login')->withErrors([
                    'code' => 'Too many attempts or code expired. Please log in again.',
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please try again.'],
            ]);
        }

        $staff = Staff::where('staff_id', $staffId)->where('isactive', 1)->first();

        if (! $staff) {
            $this->twoFactor->clearToken((int) $staffId);
            $request->session()->forget(['2fa.staff_id', '2fa.remember']);

            return redirect()->route('scp.login')->withErrors([
                'code' => 'Your account has been deactivated.',
            ]);
        }

        $remember = (bool) $request->session()->pull('2fa.remember', false);
        $request->session()->forget('2fa.staff_id');

        Auth::guard('staff')->loginUsingId($staffId, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/scp');
    }

    public function resend(Request $request): RedirectResponse
    {
        $staffId = (int) $request->session()->get('2fa.staff_id');

        if (! $staffId) {
            return redirect()->route('scp.login');
        }

        $rateLimitKey = "2fa-resend:staff:{$staffId}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting another code.",
            ]);
        }

        $staff = Staff::where('staff_id', $staffId)->where('isactive', 1)->first();

        if (! $staff) {
            $request->session()->forget(['2fa.staff_id', '2fa.remember']);

            return redirect()->route('scp.login');
        }

        $code = $this->twoFactor->generateToken($staffId, preserveStrikes: true);

        Mail::raw(
            "Your osTicket login code is: {$code}\n\nThis code expires in 6 minutes.",
            fn ($message) => $message
                ->to($staff->email)
                ->subject('Your Login Verification Code')
        );

        RateLimiter::hit($rateLimitKey, 60);

        return back()->with('status', 'A new verification code has been sent to your email.');
    }
}
