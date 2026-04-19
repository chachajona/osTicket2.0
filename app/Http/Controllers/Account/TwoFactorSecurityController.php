<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\StaffTwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorSecurityController extends Controller
{
    public function __construct(
        private readonly StaffTwoFactorService $twoFactor,
    ) {}

    public function enable(Request $request): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        $this->twoFactor->enable($staff, $request->boolean('force'));

        return redirect()
            ->route('scp.account.security')
            ->with('status', 'Two-factor authentication setup started. Scan the QR code and confirm a code to finish.');
    }

    public function qrCode(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        if (is_null($staff->two_factor_secret)) {
            return response()->json([]);
        }

        return response()->json([
            'svg' => $staff->twoFactorQrCodeSvg(),
            'url' => $staff->twoFactorQrCodeUrl(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var Staff $staff */
        $staff = $request->user('staff');

        $this->twoFactor->confirm($staff, $validated['code']);

        $staff->authMigration()->updateOrCreate(
            ['staff_id' => $staff->staff_id],
            [
                'migrated_at' => now(),
                'upgrade_method' => 'totp',
            ],
        );

        return redirect()
            ->route('scp.account.security')
            ->with('status', 'Two-factor authentication enabled.')
            ->with('two_factor_recovery_codes', $staff->fresh()->recoveryCodes());
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        if (is_null($staff->two_factor_secret) || empty($staff->recoveryCodes())) {
            return response()->json([]);
        }

        return response()->json($staff->recoveryCodes());
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');
        $codes = $this->twoFactor->regenerateRecoveryCodes($staff);

        return redirect()
            ->route('scp.account.security')
            ->with('status', 'Recovery codes regenerated.')
            ->with('two_factor_recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        $this->twoFactor->disable($staff);

        $staff->authMigration()->updateOrCreate(
            ['staff_id' => $staff->staff_id],
            [
                'migrated_at' => null,
                'upgrade_method' => null,
            ],
        );

        return redirect()
            ->route('scp.account.security')
            ->with('status', 'Two-factor authentication disabled.');
    }
}
