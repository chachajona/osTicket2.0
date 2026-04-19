<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAppChallengeService
{
    private const TTL_SECONDS = 360;

    private const MAX_STRIKES = 3;

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_LOCKED_OUT = 'locked_out';

    public const STATUS_EXPIRED = 'expired';

    public function begin(int $staffId): void
    {
        Cache::put($this->challengeKey($staffId), [
            'strikes' => 0,
            'expires_at' => now()->timestamp + self::TTL_SECONDS,
        ], self::TTL_SECONDS);
    }

    public function hasActiveChallenge(int $staffId): bool
    {
        $state = Cache::get($this->challengeKey($staffId));

        if (! is_array($state)) {
            return false;
        }

        if ((int) ($state['expires_at'] ?? 0) <= now()->timestamp) {
            $this->clear($staffId);

            return false;
        }

        return true;
    }

    public function validateAttempt(int $staffId, bool $passed): string
    {
        $state = Cache::get($this->challengeKey($staffId));

        if (! is_array($state)) {
            return self::STATUS_EXPIRED;
        }

        $remaining = (int) ($state['expires_at'] ?? 0) - now()->timestamp;

        if ($remaining <= 0) {
            $this->clear($staffId);

            return self::STATUS_EXPIRED;
        }

        if ($passed) {
            $this->clear($staffId);

            return self::STATUS_VALID;
        }

        $state['strikes'] = (int) ($state['strikes'] ?? 0) + 1;

        if ($state['strikes'] >= self::MAX_STRIKES) {
            $this->clear($staffId);
            Log::info('App 2FA lockout: max attempts exceeded', ['staff_id' => $staffId]);

            return self::STATUS_LOCKED_OUT;
        }

        Cache::put($this->challengeKey($staffId), $state, $remaining);

        return self::STATUS_INVALID;
    }

    public function clear(int $staffId): void
    {
        Cache::forget($this->challengeKey($staffId));
    }

    private function challengeKey(int $staffId): string
    {
        return "2fa-app.staff.{$staffId}";
    }
}
