<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAuthService
{
    private const TTL_SECONDS = 360;

    private const MAX_STRIKES = 3;

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_LOCKED_OUT = 'locked_out';

    public const STATUS_EXPIRED = 'expired';

    public function generateToken(int $staffId, bool $preserveStrikes = false): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $strikes = 0;

        if ($preserveStrikes) {
            $existing = Cache::get($this->tokenKey($staffId));

            if (is_array($existing)) {
                $strikes = (int) ($existing['strikes'] ?? 0);
            }
        }

        Cache::put($this->tokenKey($staffId), [
            'otp' => $code,
            'strikes' => $strikes,
        ], self::TTL_SECONDS);

        return $code;
    }

    public function validateToken(int $staffId, string $token): bool
    {
        return $this->validateTokenState($staffId, $token) === self::STATUS_VALID;
    }

    public function validateTokenState(int $staffId, string $token): string
    {
        $key = $this->tokenKey($staffId);
        $state = Cache::get($key);

        if (! $state) {
            return self::STATUS_EXPIRED;
        }

        if (hash_equals($state['otp'], $token)) {
            Cache::forget($key);

            return self::STATUS_VALID;
        }

        $state['strikes']++;

        if ($state['strikes'] >= self::MAX_STRIKES) {
            Cache::forget($key);
            Log::info('2FA lockout: max attempts exceeded', ['staff_id' => $staffId]);

            return self::STATUS_LOCKED_OUT;
        }

        Cache::put($key, $state, self::TTL_SECONDS);

        return self::STATUS_INVALID;
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
