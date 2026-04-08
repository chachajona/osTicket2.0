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

        // Record the absolute expiry timestamp alongside the OTP so
        // validateTokenState() can preserve the remaining lifetime on
        // invalid attempts instead of resetting it to a full TTL window.
        // Resend intentionally generates a brand new code with a fresh
        // expires_at value - that path is the supported way to extend
        // the challenge, not silent TTL drift from failed guesses.
        Cache::put($this->tokenKey($staffId), [
            'otp' => $code,
            'strikes' => $strikes,
            'expires_at' => now()->timestamp + self::TTL_SECONDS,
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

        // Re-check expiry against the absolute timestamp we stored during
        // generation so the token window is bounded by the original issue
        // time regardless of how many invalid attempts have landed since.
        // Older cache entries that predate the expires_at field fall back
        // to the legacy full-TTL behaviour so deploy/rollover does not
        // strand staff mid-challenge.
        $expiresAt = (int) ($state['expires_at'] ?? 0);
        $remaining = $expiresAt > 0
            ? $expiresAt - now()->timestamp
            : self::TTL_SECONDS;

        if ($remaining <= 0) {
            Cache::forget($key);

            return self::STATUS_EXPIRED;
        }

        $state['strikes']++;

        if ($state['strikes'] >= self::MAX_STRIKES) {
            Cache::forget($key);
            Log::info('2FA lockout: max attempts exceeded', ['staff_id' => $staffId]);

            return self::STATUS_LOCKED_OUT;
        }

        Cache::put($key, $state, $remaining);

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
