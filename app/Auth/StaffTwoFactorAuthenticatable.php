<?php

namespace App\Auth;

use App\Models\StaffTwoFactorCredential;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

trait StaffTwoFactorAuthenticatable
{
    protected bool $hasUnreadableTwoFactorCredential = false;

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
        return $this->readTwoFactorCredentialAttribute('two_factor_secret');
    }

    /**
     * @return array<int, string>|null
     */
    public function getTwoFactorRecoveryCodesAttribute(): ?array
    {
        return $this->readTwoFactorCredentialAttribute('two_factor_recovery_codes');
    }

    public function getTwoFactorConfirmedAtAttribute(): mixed
    {
        return $this->readTwoFactorCredentialAttribute('two_factor_confirmed_at');
    }

    public function hasUnreadableTwoFactorCredential(): bool
    {
        return $this->hasUnreadableTwoFactorCredential;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertTwoFactorCredential(array $attributes): StaffTwoFactorCredential
    {
        $staffId = $this->getAuthIdentifier();
        $credential = new StaffTwoFactorCredential([
            'staff_id' => $staffId,
        ]);
        $credential->forceFill($attributes);

        $timestamp = Carbon::now();
        $values = [[
            ...$credential->getAttributes(),
            $credential->getUpdatedAtColumn() => $timestamp,
            $credential->getCreatedAtColumn() => $timestamp,
        ]];
        $createdAtColumn = $credential->getCreatedAtColumn();
        $updateColumns = array_values(array_filter(
            array_keys($values[0]),
            fn (string $column): bool => $column !== 'staff_id' && $column !== $createdAtColumn,
        ));

        $credential->newQuery()->upsert($values, ['staff_id'], $updateColumns);

        $credential = $this->twoFactorCredential()->first();
        $this->hasUnreadableTwoFactorCredential = false;

        $this->setRelation('twoFactorCredential', $credential);

        return $credential;
    }

    protected function readTwoFactorCredentialAttribute(string $attribute): mixed
    {
        if ($this->hasUnreadableTwoFactorCredential) {
            return null;
        }

        $credential = $this->loadMissing('twoFactorCredential')->twoFactorCredential;

        if (! $credential) {
            return null;
        }

        try {
            return $credential->getAttribute($attribute);
        } catch (DecryptException $exception) {
            $this->markUnreadableTwoFactorCredential($attribute, $exception);

            return null;
        }
    }

    protected function markUnreadableTwoFactorCredential(string $attribute, DecryptException $exception): void
    {
        if ($this->hasUnreadableTwoFactorCredential) {
            return;
        }

        $this->hasUnreadableTwoFactorCredential = true;

        logger()->warning('Unreadable staff two-factor credential encountered.', [
            'staff_id' => $this->getAuthIdentifier(),
            'attribute' => $attribute,
            'message' => $exception->getMessage(),
        ]);
    }
}
