<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Staff;
use App\Models\StaffAuthMigration;

class LegacyEmailTwoFactorBannerDismisser
{
    public function dismissIfVerifiedInLegacy(Staff $staff): void
    {
        try {
            if (! $this->hasVerifiedLegacyBackend($staff)) {
                return;
            }

            $migration = StaffAuthMigration::query()->firstOrCreate([
                'staff_id' => $staff->staff_id,
            ]);

            if (! is_null($migration->dismissed_migration_banner_at)) {
                return;
            }

            $migration->forceFill([
                'migrated_at' => now(),
                'dismissed_migration_banner_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            logger()->warning('Unable to inspect legacy email two-factor state.', [
                'staff_id' => $staff->staff_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hasVerifiedLegacyBackend(Staff $staff): bool
    {
        $namespace = "staff.{$staff->staff_id}";

        return Config::query()
            ->namespace($namespace)
            ->get(['key', 'value'])
            ->contains(function (Config $config): bool {
                $decoded = json_decode((string) $config->value, true);

                return is_array($decoded) && (int) ($decoded['verified'] ?? 0) > 0;
            });
    }
}
