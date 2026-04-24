<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');
        $staff->loadMissing(['twoFactorCredential', 'authMigration']);
        $isPendingTwoFactorSetup = ! is_null($staff->two_factor_secret) && is_null($staff->two_factor_confirmed_at);

        return Inertia::render('Account/Security/Index', [
            'twoFactor' => [
                'enabled' => $staff->hasTotpEnabled(),
                'pending' => $isPendingTwoFactorSetup,
                'confirmedAt' => $staff->two_factor_confirmed_at?->toIso8601String(),
                'recoveryCodesCount' => count($staff->recoveryCodes()),
                'qrCodeSvg' => $isPendingTwoFactorSetup ? $staff->twoFactorQrCodeSvg() : null,
                'setupKey' => $isPendingTwoFactorSetup ? $staff->two_factor_secret : null,
            ],
            'migration' => [
                'isMigrated' => $staff->isMigrated(),
                'migratedAt' => $staff->authMigration?->migrated_at?->toIso8601String(),
                'upgradeMethod' => $staff->authMigration?->upgrade_method,
            ],
            'revealedRecoveryCodes' => $request->session()->get('two_factor_recovery_codes', []),
        ]);
    }
}
