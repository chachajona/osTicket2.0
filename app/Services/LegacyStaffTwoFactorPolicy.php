<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Staff;

class LegacyStaffTwoFactorPolicy
{
    private const BACKEND_APP = 'auth.agent';

    private const REQUIRE_AGENT_2FA_KEY = 'require_agent_2fa';

    public function challengeMethodFor(Staff $staff): ?string
    {
        $defaultBackend = $this->defaultBackendFor($staff);

        if ($defaultBackend !== null && $this->backendIsVerified($staff, $defaultBackend)) {
            $challengeMethod = $this->availableChallengeMethodFor($staff, $defaultBackend);

            if ($challengeMethod !== null) {
                return $challengeMethod;
            }
        }

        if (! $this->requiresAgentsGlobally()) {
            return null;
        }

        return $staff->hasTotpEnabled() ? 'app' : 'email';
    }

    private function requiresAgentsGlobally(): bool
    {
        $value = Config::query()
            ->namespace('core')
            ->where('key', self::REQUIRE_AGENT_2FA_KEY)
            ->value('value');

        if (! is_string($value)) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value !== '0';
    }

    private function defaultBackendFor(Staff $staff): ?string
    {
        $value = Config::query()
            ->namespace("staff.{$staff->staff_id}")
            ->where('key', 'default_2fa')
            ->value('value');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function backendIsVerified(Staff $staff, string $backend): bool
    {
        $value = Config::query()
            ->namespace("staff.{$staff->staff_id}")
            ->where('key', $backend)
            ->value('value');

        if (! is_string($value)) {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && (int) ($decoded['verified'] ?? 0) > 0;
    }

    private function mapBackendToChallengeMethod(string $backend): string
    {
        return $backend === self::BACKEND_APP ? 'app' : 'email';
    }

    private function availableChallengeMethodFor(Staff $staff, string $backend): ?string
    {
        $challengeMethod = $this->mapBackendToChallengeMethod($backend);

        if ($challengeMethod !== 'app') {
            return $challengeMethod;
        }

        return $staff->hasTotpEnabled() ? 'app' : null;
    }
}
