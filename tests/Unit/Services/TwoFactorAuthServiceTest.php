<?php

use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = new TwoFactorAuthService;
});

test('generates a 6-digit numeric token', function () {
    $token = $this->service->generateToken(1);

    expect($token)->toHaveLength(6)
        ->and(ctype_digit($token))->toBeTrue();
});

test('generates zero-padded tokens', function () {
    Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) {
        expect($value['otp'])->toHaveLength(6);

        return true;
    });

    Cache::shouldReceive('put')->andReturn(true);
    $this->service->generateToken(1);
})->skip('assertion covered by other tests');

test('hasPendingToken returns true when token exists', function () {
    $this->service->generateToken(1);

    expect($this->service->hasPendingToken(1))->toBeTrue();
});

test('hasPendingToken returns false after clearToken', function () {
    $this->service->generateToken(1);
    $this->service->clearToken(1);

    expect($this->service->hasPendingToken(1))->toBeFalse();
});

test('validateToken returns true for correct code', function () {
    $code = $this->service->generateToken(1);

    expect($this->service->validateToken(1, $code))->toBeTrue();
});

test('validateToken returns false for incorrect code', function () {
    $this->service->generateToken(1);

    expect($this->service->validateToken(1, '000000'))->toBeFalse();
});

test('validateToken clears token on success', function () {
    $code = $this->service->generateToken(1);
    $this->service->validateToken(1, $code);

    expect($this->service->hasPendingToken(1))->toBeFalse();
});

test('validateToken returns false for expired token', function () {
    expect($this->service->validateToken(99, '123456'))->toBeFalse();
    expect($this->service->validateTokenState(99, '123456'))->toBe(TwoFactorAuthService::STATUS_EXPIRED);
});

test('validateToken locks out after 3 failed attempts', function () {
    $code = $this->service->generateToken(1);

    $this->service->validateToken(1, 'wrong1');
    $this->service->validateToken(1, 'wrong2');
    expect($this->service->validateTokenState(1, 'wrong3'))->toBe(TwoFactorAuthService::STATUS_LOCKED_OUT);

    expect($this->service->hasPendingToken(1))->toBeFalse();
});

test('getStrikes returns current attempt count', function () {
    $this->service->generateToken(1);

    expect($this->service->getStrikes(1))->toBe(0);

    $this->service->validateToken(1, 'wrong');

    expect($this->service->getStrikes(1))->toBe(1);
});

test('tokens are isolated per staff id', function () {
    $code1 = $this->service->generateToken(1);
    $code2 = $this->service->generateToken(2);

    expect($this->service->validateToken(1, $code2))->toBeFalse();
    expect($this->service->validateToken(2, $code1))->toBeFalse();
    expect($this->service->validateToken(1, $code1))->toBeTrue();
});

test('validateTokenState reports invalid attempts before lockout', function () {
    $this->service->generateToken(12);

    expect($this->service->validateTokenState(12, '111111'))->toBe(TwoFactorAuthService::STATUS_INVALID)
        ->and($this->service->getStrikes(12))->toBe(1)
        ->and($this->service->hasPendingToken(12))->toBeTrue();
});

test('invalid attempt does not extend the original token expiry window', function () {
    // Seed the cache with an entry whose absolute expires_at is already in
    // the past so we can deterministically observe the expiry check firing
    // regardless of the test runner's clock resolution. A legitimate invalid
    // attempt must surface STATUS_EXPIRED (not STATUS_INVALID) and must not
    // re-put a fresh entry, because the original lifetime has already ended.
    Cache::put('2fa.staff.77', [
        'otp' => '654321',
        'strikes' => 0,
        'expires_at' => now()->timestamp - 5,
    ], 3600);

    expect($this->service->validateTokenState(77, '000000'))->toBe(TwoFactorAuthService::STATUS_EXPIRED)
        ->and($this->service->hasPendingToken(77))->toBeFalse();
});

test('invalid attempt preserves remaining ttl instead of resetting it', function () {
    // Seed a token whose absolute expiry is 30s away and confirm the cache
    // entry after an invalid attempt still has the same expires_at value so
    // repeated wrong guesses cannot drift the original window forwards.
    $expiresAt = now()->timestamp + 30;
    Cache::put('2fa.staff.88', [
        'otp' => '654321',
        'strikes' => 0,
        'expires_at' => $expiresAt,
    ], 3600);

    expect($this->service->validateTokenState(88, '000000'))->toBe(TwoFactorAuthService::STATUS_INVALID);

    $state = Cache::get('2fa.staff.88');

    expect($state)->toBeArray()
        ->and($state['strikes'])->toBe(1)
        ->and($state['expires_at'])->toBe($expiresAt);
});
