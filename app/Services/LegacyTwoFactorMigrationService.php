<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\RecoveryCode;

class LegacyTwoFactorMigrationService
{
    public function migrateIfNeeded(Staff $staff): void
    {
        try {
            if ($this->migrateTotpIfNeeded($staff)) {
                return;
            }

            $this->dismissEmailBannerIfNeeded($staff);
        } catch (\Throwable $exception) {
            Log::warning('Legacy 2FA migration failed', [
                'staff_id' => $staff->staff_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function migrateTotpIfNeeded(Staff $staff): bool
    {
        if ($staff->hasTotpEnabled()) {
            return true;
        }

        $decoded = $this->legacyBackendConfig($staff, 'auth.agent');

        if (! $this->isVerified($decoded)) {
            return false;
        }

        $base32 = (string) ($decoded['config']['key'] ?? '');

        if ($base32 === '') {
            return false;
        }

        $staff->upsertTwoFactorCredential([
            'two_factor_secret' => $base32,
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

        return true;
    }

    private function dismissEmailBannerIfNeeded(Staff $staff): void
    {
        if (! is_null($staff->loadMissing('authMigration')->authMigration?->dismissed_migration_banner_at)) {
            return;
        }

        $decoded = $this->legacyBackendConfig($staff, 'email');

        if (! $this->isVerified($decoded)) {
            return;
        }

        $staff->authMigration()->updateOrCreate(
            ['staff_id' => $staff->staff_id],
            [
                'migrated_at' => now(),
                'dismissed_migration_banner_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyBackendConfig(Staff $staff, string $backend): array
    {
        $value = Config::query()
            ->namespace("staff.{$staff->staff_id}")
            ->where('key', $backend)
            ->value('value');

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function isVerified(array $decoded): bool
    {
        return (int) ($decoded['verified'] ?? 0) > 0;
    }
}
