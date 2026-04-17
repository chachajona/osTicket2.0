<?php

use App\Models\Staff;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Auth;
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
    $response->assertSessionHasErrors([
        'code' => 'Too many attempts or code expired. Please log in again.',
    ]);
    expect($service->hasPendingToken(5))->toBeFalse();
});

test('2fa lockout feedback is available on the login page inertia props', function () {
    $service = app(TwoFactorAuthService::class);
    $service->generateToken(6);

    $this->withSession(['2fa.staff_id' => 6])->post('/scp/2fa', ['code' => 'wrong1']);
    $this->withSession(['2fa.staff_id' => 6])->post('/scp/2fa', ['code' => 'wrong2']);
    $this->withSession(['2fa.staff_id' => 6])->post('/scp/2fa', ['code' => 'wrong3']);

    $response = $this->followingRedirects()
        ->withHeaders(inertiaHeaders())
        ->withSession(['2fa.staff_id' => 6])
        ->post('/scp/2fa', ['code' => 'wrong4']);

    $response->assertOk();
    $response->assertJsonPath('component', 'Auth/Login');
    $response->assertJsonPath('props.errors.code', 'Too many attempts or code expired. Please log in again.');
});

test('2fa remember me stores a reusable staff remember token', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 43,
        'username' => 'staff43',
        'firstname' => 'Remember',
        'lastname' => 'Token',
        'email' => 'remember@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $service = app(TwoFactorAuthService::class);
    $code = $service->generateToken(43);

    $response = $this->withSession([
        '2fa.staff_id' => 43,
        '2fa.remember' => true,
    ])->post('/scp/2fa', ['code' => $code]);

    $response->assertRedirect('/scp');

    $provider = Auth::guard('staff')->getProvider();
    $staff = $provider->retrieveById(43);
    $rememberToken = $staff?->getRememberToken();
    $rememberCookieName = Auth::guard('staff')->getRecallerName();

    expect($rememberToken)->toBeString()->not->toBe('');
    expect($provider->retrieveByToken(43, $rememberToken))->not->toBeNull();
    $response->assertCookie($rememberCookieName);
});

test('provider-hydrated remember token does not break later staff saves', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 44,
        'username' => 'staff44',
        'firstname' => 'Hydrated',
        'lastname' => 'Remember',
        'email' => 'hydrate@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $provider = Auth::guard('staff')->getProvider();
    $provider->updateRememberToken(Staff::on('legacy')->findOrFail(44), 'remember-token-44');

    $staff = $provider->retrieveById(44);

    expect($staff?->getRememberToken())->toBe('remember-token-44');

    $staff->firstname = 'Updated';
    $staff->save();

    expect(Staff::on('legacy')->findOrFail(44)->firstname)->toBe('Updated');
});

test('inactive staff cannot be restored from session or remember token', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 45,
        'username' => 'staff45',
        'firstname' => 'Inactive',
        'lastname' => 'Restore',
        'email' => 'inactive-restore@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 0,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $provider = Auth::guard('staff')->getProvider();
    $provider->updateRememberToken(Staff::on('legacy')->findOrFail(45), 'remember-token-45');

    expect($provider->retrieveById(45))->toBeNull();
    expect($provider->retrieveByToken(45, 'remember-token-45'))->toBeNull();
});

test('2fa verify keeps session active after a non-locking invalid attempt', function () {
    $service = app(TwoFactorAuthService::class);
    $service->generateToken(9);

    $response = $this->withSession(['2fa.staff_id' => 9])
        ->post('/scp/2fa', ['code' => '123123']);

    $response->assertSessionHasErrors(['code']);
    expect($service->hasPendingToken(9))->toBeTrue()
        ->and($service->getStrikes(9))->toBe(1);
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
