<?php

use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('2fa verify logs in staff with correct code', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 42,
        'username' => 'staff42',
        'firstname' => 'Two',
        'lastname' => 'Factor',
        'email' => 'twofactor@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $service = app(TwoFactorAuthService::class);
    $code = $service->generateToken(42);

    $response = $this->withSession(['2fa.staff_id' => 42])
        ->post('/scp/2fa', ['code' => $code]);

    $response->assertRedirect('/scp');
});

test('2fa lockout after 3 failed attempts redirects to login with error', function () {
    $service = app(TwoFactorAuthService::class);
    $service->generateToken(5);

    $this->withSession(['2fa.staff_id' => 5])->post('/scp/2fa', ['code' => 'wrong1']);
    $this->withSession(['2fa.staff_id' => 5])->post('/scp/2fa', ['code' => 'wrong2']);
    $this->withSession(['2fa.staff_id' => 5])->post('/scp/2fa', ['code' => 'wrong3']);

    $response = $this->withSession(['2fa.staff_id' => 5])->post('/scp/2fa', ['code' => 'wrong4']);

    $response->assertRedirect('/scp/login');
    expect($service->hasPendingToken(5))->toBeFalse();
});

test('2fa resend requires active session', function () {
    $response = $this->post('/scp/2fa/resend');

    $response->assertRedirect('/scp/login');
});

test('password reset link is invalid after expiry', function () {
    $response = $this->get('/scp/password/reset/expired-token-xyz');

    $response->assertRedirect('/scp/password/forgot');
});

test('password reset requires matching passwords', function () {
    $token = 'valid-token';
    Cache::put("password_reset.{$token}", 1, 60);

    $response = $this->post('/scp/password/reset', [
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('password reset requires minimum length', function () {
    $token = 'valid-token-2';
    Cache::put("password_reset.{$token}", 1, 60);

    $response = $this->post('/scp/password/reset', [
        'token' => $token,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors(['password']);
});
