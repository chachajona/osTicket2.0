<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\RecoveryCode;

class StaffTwoFactorService
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function enable(Staff $staff, bool $force = false): void
    {
        if (! empty($staff->two_factor_secret) && ! $force) {
            return;
        }

        $secretLength = (int) config('fortify-options.two-factor-authentication.secret-length', 16);

        $staff->upsertTwoFactorCredential([
            'two_factor_secret' => $this->provider->generateSecretKey($secretLength),
            'two_factor_recovery_codes' => Collection::times(8, fn () => RecoveryCode::generate())->all(),
            'two_factor_confirmed_at' => null,
        ]);

        TwoFactorAuthenticationEnabled::dispatch($staff);
    }

    /**
     * @throws ValidationException
     */
    public function confirm(Staff $staff, string $code): void
    {
        if (empty($staff->two_factor_secret) || ! $this->provider->verify($staff->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        $staff->upsertTwoFactorCredential([
            'two_factor_confirmed_at' => now(),
        ]);

        TwoFactorAuthenticationConfirmed::dispatch($staff);
    }

    public function disable(Staff $staff): void
    {
        if (is_null($staff->two_factor_secret) &&
            is_null($staff->two_factor_recovery_codes) &&
            is_null($staff->two_factor_confirmed_at)) {
            return;
        }

        $staff->upsertTwoFactorCredential([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        TwoFactorAuthenticationDisabled::dispatch($staff);
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(Staff $staff): array
    {
        $codes = Collection::times(8, fn () => RecoveryCode::generate())->all();

        $staff->upsertTwoFactorCredential([
            'two_factor_recovery_codes' => $codes,
        ]);

        RecoveryCodesGenerated::dispatch($staff);

        return $codes;
    }

    public function hasValidOneTimePassword(Staff $staff, string $code): bool
    {
        return ! empty($staff->two_factor_secret)
            && $this->provider->verify($staff->two_factor_secret, $code);
    }

    public function consumeRecoveryCode(Staff $staff, string $code): bool
    {
        $matchingCode = collect($staff->recoveryCodes())->first(
            fn (string $storedCode): bool => hash_equals($storedCode, $code)
        );

        if (! $matchingCode) {
            return false;
        }

        $staff->replaceRecoveryCode($matchingCode);

        return true;
    }
}
