<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\StaffTwoFactorService;
use App\Services\TwoFactorAppChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAppController extends Controller
{
    public function __construct(
        private readonly StaffTwoFactorService $twoFactor,
        private readonly TwoFactorAppChallengeService $challenge,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa_app.staff_id')) {
            return redirect()->route('scp.login');
        }

        return Inertia::render('Auth/TwoFactorApp');
    }

    public function verify(Request $request): RedirectResponse
    {
        $staffId = (int) $request->session()->get('2fa_app.staff_id');

        if ($staffId === 0 || ! $this->challenge->hasActiveChallenge($staffId)) {
            $request->session()->forget(['2fa_app.staff_id', '2fa_app.remember']);

            return redirect()->route('scp.login')->withErrors([
                'code' => 'Too many attempts or code expired. Please log in again.',
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $staff = Staff::where('staff_id', $staffId)
            ->where('isactive', 1)
            ->first();

        if (! $staff || ! $staff->hasTotpEnabled()) {
            $this->challenge->clear($staffId);
            $request->session()->forget(['2fa_app.staff_id', '2fa_app.remember']);

            return redirect()->route('scp.login')->withErrors([
                'code' => 'Your account is no longer available for app-based authentication.',
            ]);
        }

        $code = trim($validated['code']);
        $usesRecoveryCode = false;
        $isValid = $this->twoFactor->hasValidOneTimePassword($staff, $code);

        if (! $isValid) {
            $usesRecoveryCode = $this->twoFactor->hasMatchingRecoveryCode($staff, $code);
            $isValid = $usesRecoveryCode;
        }

        $status = $this->challenge->validateAttempt($staffId, $isValid);

        if ($status !== TwoFactorAppChallengeService::STATUS_VALID) {
            if (in_array($status, [
                TwoFactorAppChallengeService::STATUS_LOCKED_OUT,
                TwoFactorAppChallengeService::STATUS_EXPIRED,
            ], true)) {
                $request->session()->forget(['2fa_app.staff_id', '2fa_app.remember']);

                return redirect()->route('scp.login')->withErrors([
                    'code' => 'Too many attempts or code expired. Please log in again.',
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['Invalid authentication code or recovery code.'],
            ]);
        }

        if ($usesRecoveryCode) {
            $this->twoFactor->consumeRecoveryCode($staff, $code);
        }

        $remember = (bool) $request->session()->pull('2fa_app.remember', false);
        $request->session()->forget('2fa_app.staff_id');

        Auth::guard('staff')->loginUsingId($staffId, $remember);
        $intendedUrl = $this->resolveIntendedUrl($request);
        $request->session()->regenerate();

        return redirect()->to($intendedUrl);
    }

    private function resolveIntendedUrl(Request $request): string
    {
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl) || rtrim($intendedUrl, '/') === rtrim(url('/'), '/')) {
            return route('scp.dashboard');
        }

        return $intendedUrl;
    }
}
