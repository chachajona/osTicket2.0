<?php

namespace App\Auth;

use App\Models\StaffTwoFactorCredential;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

trait StaffTwoFactorAuthenticatable
{
    /**
     * Get the sibling record that stores Fortify-managed two-factor state.
     *
     * @return HasOne<StaffTwoFactorCredential, $this>
     */
    public function twoFactorCredential(): HasOne
    {
        return $this->hasOne(StaffTwoFactorCredential::class, 'staff_id', 'staff_id');
    }

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        if (Fortify::confirmsTwoFactorAuthentication()) {
            return ! is_null($this->two_factor_secret) &&
                ! is_null($this->two_factor_confirmed_at);
        }

        return ! is_null($this->two_factor_secret);
    }

    /**
     * @return array<int, string>
     */
    public function recoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    public function replaceRecoveryCode(string $code): void
    {
        $codes = $this->recoveryCodes();
        $index = collect($codes)->search(
            fn (string $storedCode): bool => hash_equals($storedCode, $code)
        );

        if ($index === false) {
            return;
        }

        $codes[$index] = RecoveryCode::generate();

        $this->upsertTwoFactorCredential([
            'two_factor_recovery_codes' => array_values($codes),
        ]);
    }

    public function twoFactorQrCodeSvg(): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd
            )
        ))->writeString($this->twoFactorQrCodeUrl());

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    public function twoFactorQrCodeUrl(): string
    {
        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            config('app.name'),
            $this->{Fortify::username()},
            (string) $this->two_factor_secret,
        );
    }

    public function getTwoFactorSecretAttribute(): ?string
    {
        return $this->loadMissing('twoFactorCredential')->twoFactorCredential?->two_factor_secret;
    }

    /**
     * @return array<int, string>|null
     */
    public function getTwoFactorRecoveryCodesAttribute(): ?array
    {
        return $this->loadMissing('twoFactorCredential')->twoFactorCredential?->two_factor_recovery_codes;
    }

    public function getTwoFactorConfirmedAtAttribute(): mixed
    {
        return $this->loadMissing('twoFactorCredential')->twoFactorCredential?->two_factor_confirmed_at;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertTwoFactorCredential(array $attributes): StaffTwoFactorCredential
    {
        $credential = $this->relationLoaded('twoFactorCredential')
            ? $this->getRelation('twoFactorCredential')
            : $this->twoFactorCredential()->first();

        $credential ??= new StaffTwoFactorCredential([
            'staff_id' => $this->getAuthIdentifier(),
        ]);

        $credential->forceFill($attributes);
        $credential->staff_id = $this->getAuthIdentifier();
        $credential->save();

        $this->setRelation('twoFactorCredential', $credential);

        return $credential;
    }
}
