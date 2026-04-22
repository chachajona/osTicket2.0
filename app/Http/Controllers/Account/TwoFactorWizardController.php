<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorWizardController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');
        $staff->loadMissing('twoFactorCredential');

        $hasSecret = ! is_null($staff->two_factor_secret);
        $confirmed = ! is_null($staff->two_factor_confirmed_at);
        $step = (int) $request->query('step', $confirmed ? 5 : ($hasSecret ? 3 : 1));
        $step = max(1, min(5, $step));

        return Inertia::render('Account/Security/TwoFactorWizard', [
            'step' => $step,
            'twoFactor' => [
                'enabled' => $confirmed,
                'pending' => $hasSecret && ! $confirmed,
                'method' => $staff->hasTotpEnabled() ? 'app' : null,
                'qrCodeSvg' => $hasSecret && ! $confirmed ? $staff->twoFactorQrCodeSvg() : null,
                'qrCodeUrl' => $hasSecret && ! $confirmed ? $staff->twoFactorQrCodeUrl() : null,
                'setupKey' => $hasSecret && ! $confirmed ? $staff->two_factor_secret : null,
                'recoveryCodes' => $request->session()->get('two_factor_recovery_codes', []),
            ],
        ]);
    }
}
