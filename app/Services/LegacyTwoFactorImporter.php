<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\RecoveryCode;

class LegacyTwoFactorImporter
{
    public function importIfNeeded(Staff $staff): void
    {
        if (! is_null($staff->two_factor_secret) && ! is_null($staff->two_factor_confirmed_at)) {
            return;
        }

        $secret = DB::connection('legacy')
            ->table('staff')
            ->where('staff_id', $staff->staff_id)
            ->value(config('auth.legacy_totp_column', 'totp_secret'));

        if (! is_string($secret) || trim($secret) === '') {
            return;
        }

        $staff->upsertTwoFactorCredential([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => Collection::times(8, fn () => RecoveryCode::generate())->all(),
            'two_factor_confirmed_at' => now(),
        ]);

        $staff->authMigration()->updateOrCreate(
            ['staff_id' => $staff->staff_id],
            [
                'migrated_at' => now(),
                'upgrade_method' => 'totp',
            ],
        );
    }
}
