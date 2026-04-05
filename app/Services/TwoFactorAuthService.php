<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAuthService
{
    private const TTL_SECONDS = 360;

    private const MAX_STRIKES = 3;

    public function generateToken(int $staffId): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->tokenKey($staffId), [
            'otp' => $code,
            'strikes' => 0,
        ], self::TTL_SECONDS);

        return $code;
    }

    public function validateToken(int $staffId, string $token): bool
    {
        $key = $this->tokenKey($staffId);
        $state = Cache::get($key);

        if (! $state) {
            return false;
        }

        $state['strikes']++;

        if ($state['strikes'] >= self::MAX_STRIKES) {
            Cache::forget($key);
            Log::info('2FA lockout: max attempts exceeded', ['staff_id' => $staffId]);

            return false;
        }

        Cache::put($key, $state, self::TTL_SECONDS);

        if (! hash_equals($state['otp'], $token)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    public function hasPendingToken(int $staffId): bool
    {
        return Cache::has($this->tokenKey($staffId));
    }

    public function clearToken(int $staffId): void
    {
        Cache::forget($this->tokenKey($staffId));
    }

    public function getStrikes(int $staffId): int
    {
        $state = Cache::get($this->tokenKey($staffId));

        return $state['strikes'] ?? 0;
    }

    private function tokenKey(int $staffId): string
    {
        return "2fa.staff.{$staffId}";
    }
}
