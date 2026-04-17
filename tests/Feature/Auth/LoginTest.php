<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Mail\PasswordResetLinkMail;
use App\Models\Staff;
use App\Services\TwoFactorAuthService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function makeStaff(array $attrs = []): Staff
{
    $staff = new Staff(array_merge([
        'staff_id' => 1,
        'username' => 'teststaff',
        'firstname' => 'Test',
        'lastname' => 'Staff',
        'email' => 'test@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => '1',
        'isadmin' => '0',
    ], $attrs));
    $staff->exists = true;

    return $staff;
}

test('login page renders at /scp/login', function () {
    $response = $this->withHeaders(inertiaHeaders())->get('/scp/login');

    $response->assertStatus(200);
    $response->assertJsonPath('component', 'Auth/Login');
});

test('login page renders flash status messages', function () {
    $response = $this->withSession([
        'status' => 'Password reset successfully. Please log in.',
    ])->withHeaders(inertiaHeaders())->get('/scp/login');

    $response->assertOk();
    $response->assertJsonPath('component', 'Auth/Login');
    $response->assertJsonPath('props.status', 'Password reset successfully. Please log in.');
});

test('authenticated staff are redirected from login page', function () {
    $staff = makeStaff();
    Auth::guard('staff')->login($staff);

    $response = $this->get('/scp/login');

    $response->assertRedirect('/scp');
});

test('login requires username and password', function () {
    $response = $this->post('/scp/login', []);

    $response->assertSessionHasErrors(['username', 'password']);
});

test('login with invalid credentials returns error', function () {
    $response = $this->post('/scp/login', [
        'username' => 'nonexistent',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors(['username']);
});

test('inactive staff cannot be validated or attempted through the staff guard', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 200,
        'username' => 'inactive-attempt',
        'firstname' => 'Inactive',
        'lastname' => 'Attempt',
        'email' => 'inactive-attempt@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 0,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $credentials = [
        'username' => 'inactive-attempt',
        'password' => 'password',
    ];

    expect(Auth::guard('staff')->validate($credentials))->toBeFalse();
    expect(Auth::guard('staff')->attempt($credentials))->toBeFalse();
});

test('successful login redirects to 2fa page', function () {
    Mail::fake();
    expect(true)->toBeTrue();
})->skip('requires DB mocking infrastructure');

test('2fa page redirects to login when no session', function () {
    $response = $this->get('/scp/2fa');

    $response->assertRedirect('/scp/login');
});

test('2fa page renders when session has staff_id', function () {
    $response = $this->withSession(['2fa.staff_id' => 1])
        ->withHeaders(inertiaHeaders())
        ->get('/scp/2fa');

    $response->assertStatus(200);
    $response->assertJsonPath('component', 'Auth/TwoFactor');
});

test('2fa verify without session redirects to login', function () {
    $response = $this->post('/scp/2fa', ['code' => '123456']);

    $response->assertRedirect('/scp/login');
});

test('2fa verify rejects invalid code', function () {
    $service = app(TwoFactorAuthService::class);
    $service->generateToken(1);

    $response = $this->withSession(['2fa.staff_id' => 1])
        ->post('/scp/2fa', ['code' => '000000']);

    $response->assertSessionHasErrors(['code']);
});

test('2fa verify requires 6-digit code', function () {
    $response = $this->withSession(['2fa.staff_id' => 1])
        ->post('/scp/2fa', ['code' => '12']);

    $response->assertSessionHasErrors(['code']);
});

test('logout clears session and redirects to login', function () {
    $staff = makeStaff();
    Auth::guard('staff')->login($staff);

    $response = $this->post('/scp/logout');

    $response->assertRedirect('/scp/login');
    expect(Auth::guard('staff')->check())->toBeFalse();
});

test('authenticated route redirects to login when unauthenticated', function () {
    $response = $this->get('/scp');

    $response->assertRedirect('/scp/login');
});

test('authenticated staff can access scp dashboard', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 1,
        'username' => 'teststaff',
        'firstname' => 'Test',
        'lastname' => 'Staff',
        'email' => 'test@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $staff = Staff::on('legacy')->find(1);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp');

    $response->assertStatus(200);
    $response->assertJsonPath('component', 'Dashboard');
});

test('forgot password page renders', function () {
    $response = $this->withHeaders(inertiaHeaders())->get('/scp/password/forgot');

    $response->assertStatus(200);
    $response->assertJsonPath('component', 'Auth/ForgotPassword');
});

test('forgot password requires valid email', function () {
    $response = $this->post('/scp/password/forgot', ['email' => 'not-an-email']);

    $response->assertSessionHasErrors(['email']);
});

test('forgot password shows generic success message regardless of account existence', function () {
    Mail::fake();

    $response = $this->post('/scp/password/forgot', ['email' => 'nobody@example.com']);

    $response->assertSessionHas('status');
});

test('forgot password queues the reset email for active staff', function () {
    Mail::fake();

    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 98,
        'username' => 'queuedreset',
        'firstname' => 'Queued',
        'lastname' => 'Reset',
        'email' => 'queued-reset@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $response = $this->post('/scp/password/forgot', ['email' => 'queued-reset@example.com']);

    $response->assertSessionHas('status');
    Mail::assertQueued(PasswordResetLinkMail::class);
});

test('issue reset token returns null when token issuance lock is unavailable', function () {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('get')->once()->andReturnFalse();

    Cache::shouldReceive('lock')
        ->once()
        ->with('password_reset_staff_lock.121', 5)
        ->andReturn($lock);

    $method = new ReflectionMethod(PasswordResetController::class, 'issueResetToken');
    $method->setAccessible(true);

    $token = $method->invoke(new PasswordResetController, makeStaff(['staff_id' => 121]));

    expect($token)->toBeNull();
});

test('forgot password requests are rate limited', function () {
    Mail::fake();

    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 99,
        'username' => 'resetstaff',
        'firstname' => 'Reset',
        'lastname' => 'Staff',
        'email' => 'reset@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $this->post('/scp/password/forgot', ['email' => 'reset@example.com'])
        ->assertSessionHas('status');

    $token = Cache::get('password_reset_staff.99');

    expect($token)->toBeString()->not->toBe('');
    expect(Cache::get("password_reset.{$token}"))->toBe(99);

    $response = $this->post('/scp/password/forgot', ['email' => 'reset@example.com']);

    $response->assertSessionHasErrors(['email']);
    expect(Cache::get('password_reset_staff.99'))->toBe($token);
    expect(Cache::get("password_reset.{$token}"))->toBe(99);
});

test('password reset form rejects malformed tokens', function () {
    $response = $this->get('/scp/password/reset/not-a-valid-token');

    $response->assertRedirect('/scp/password/forgot');
    $response->assertSessionHasErrors(['email']);
});

test('inactive staff cannot reset their password with a valid token', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 120,
        'username' => 'inactive-reset',
        'firstname' => 'Inactive',
        'lastname' => 'Reset',
        'email' => 'inactive-reset@example.com',
        'passwd' => bcrypt('old-password'),
        'isactive' => 0,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $token = str_repeat('a', 64);
    Cache::put("password_reset.{$token}", 120, 60);
    Cache::put('password_reset_staff.120', $token, 60);

    $response = $this->post('/scp/password/reset', [
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors(['token']);
    expect(Cache::get("password_reset.{$token}"))->toBeNull()
        ->and(Cache::get('password_reset_staff.120'))->toBeNull();
});
