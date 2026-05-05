<?php

use App\Models\Staff;
use App\Models\StaffAuthMigration;
use App\Models\StaffTwoFactorCredential;
use App\Services\StaffTwoFactorService;
use App\Services\TwoFactorAppChallengeService;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

function createLegacyStaff(array $attributes = []): Staff
{
    $staffId = $attributes['staff_id'] ?? random_int(1000, 9999);

    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => $staffId,
        'dept_id' => 1,
        'username' => "staff{$staffId}",
        'firstname' => 'Fortify',
        'lastname' => 'Tester',
        'email' => "staff{$staffId}@example.com",
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->findOrFail($staffId);
}

test('security page renders for authenticated staff', function () {
    $staff = createLegacyStaff();

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/account/security');

    $response->assertOk();
    $response->assertJsonPath('component', 'Account/Security/Index');
    $response->assertJsonPath('props.twoFactor.enabled', false);
    $response->assertJsonPath('props.migration.isMigrated', false);
});

test('enabling two-factor stores credentials on the osticket2 connection', function () {
    $staff = createLegacyStaff();

    $response = $this->actingAs($staff, 'staff')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post('/scp/account/security/two-factor/enable');

    $response->assertRedirect('/scp/account/security');

    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential)->not->toBeNull()
        ->and($credential?->two_factor_secret)->toBeString()
        ->and($credential?->two_factor_confirmed_at)->toBeNull()
        ->and($credential?->two_factor_recovery_codes)->toHaveCount(8);
});

test('confirming two-factor marks the staff member as migrated', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);

    $otp = app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret);

    $response = $this->actingAs($staff->fresh(), 'staff')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post('/scp/account/security/two-factor/confirm', [
            'code' => $otp,
        ]);

    $response->assertRedirect('/scp/account/security');
    $response->assertSessionHas('two_factor_recovery_codes');

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($staff->fresh()->hasTotpEnabled())->toBeTrue()
        ->and($migration)->not->toBeNull()
        ->and($migration?->upgrade_method)->toBe('totp')
        ->and($migration?->migrated_at)->not->toBeNull();

    $this->travelBack();
});

test('confirming two-factor clears the cached migration banner result', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $cacheKey = "auth.migration_banner.{$staff->staff_id}";

    $this->actingAs($staff, 'staff')
        ->get('/scp')
        ->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', false))
        ->assertSessionHas($cacheKey, fn (array $cache): bool => $cache['visible'] === false && is_int($cache['cached_at']));

    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);

    $otp = app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret);

    $this->actingAs($staff->fresh(), 'staff')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post('/scp/account/security/two-factor/confirm', [
            'code' => $otp,
        ])
        ->assertRedirect('/scp/account/security')
        ->assertSessionMissing($cacheKey);

    $this->actingAs($staff->fresh(), 'staff')
        ->get('/scp')
        ->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', false))
        ->assertSessionHas($cacheKey, fn (array $cache): bool => $cache['visible'] === false && is_int($cache['cached_at']));

    $this->travelBack();
});

test('security page only exposes setup secret while two-factor confirmation is pending', function () {
    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);

    $pendingResponse = $this->actingAs($staff->fresh(), 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/account/security');

    $pendingResponse->assertOk();

    $pendingTwoFactor = Arr::get($pendingResponse->json(), 'props.twoFactor');

    expect($pendingTwoFactor)
        ->not->toBeNull()
        ->and($pendingTwoFactor['pending'])->toBeTrue()
        ->and($pendingTwoFactor['setupKey'])->toBeString()->not->toBe('')
        ->and($pendingTwoFactor['qrCodeSvg'])->toBeString()->not->toBe('')
        ->and($pendingTwoFactor)->not->toHaveKey('qrCodeUrl');

    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));

    $confirmedResponse = $this->actingAs($staff->fresh(), 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/account/security');

    $confirmedResponse->assertOk();
    $confirmedResponse->assertJsonPath('props.twoFactor.pending', false);
    $confirmedResponse->assertJsonPath('props.twoFactor.enabled', true);
    $confirmedResponse->assertJsonPath('props.twoFactor.setupKey', null);
    $confirmedResponse->assertJsonPath('props.twoFactor.qrCodeSvg', null);
    expect(Arr::get($confirmedResponse->json(), 'props.twoFactor'))->not->toHaveKey('qrCodeUrl');
});

test('login routes totp-enrolled staff to the app challenge', function () {
    $this->travelTo(now());
    Mail::fake();

    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);
    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));

    $response = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $response->assertRedirect('/scp/2fa-app');
    $response->assertSessionHas('2fa_app.staff_id', $staff->staff_id);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();

    $this->travelBack();
});

test('totp challenge accepts a valid app code', function () {
    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);
    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));
    $appCode = app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret);
    Cache::forget('fortify.2fa_codes.'.md5($appCode));

    app(TwoFactorAppChallengeService::class)->begin($staff->staff_id);

    $response = $this->withSession([
        '2fa_app.staff_id' => $staff->staff_id,
        '2fa_app.remember' => true,
        'url.intended' => '/scp',
    ])->post('/scp/2fa-app', [
        'code' => $appCode,
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and((string) $response->headers->get('Location'))->toEndWith('/scp');
    expect($response->baseResponse->getSession()->get('errors'))->toBeNull();
    expect(auth('staff')->check())->toBeTrue();
});

test('totp challenge accepts and rotates a recovery code', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);
    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));

    $recoveryCode = $staff->fresh()->recoveryCodes()[0];
    app(TwoFactorAppChallengeService::class)->begin($staff->staff_id);

    $response = $this->withSession([
        '2fa_app.staff_id' => $staff->staff_id,
        'url.intended' => '/scp',
    ])->post('/scp/2fa-app', [
        'code' => $recoveryCode,
    ]);

    $response->assertRedirect('/scp');

    $replacementCodes = $staff->fresh()->recoveryCodes();

    expect($replacementCodes)->toHaveCount(8)
        ->and($replacementCodes)->not->toContain($recoveryCode);

    $this->travelBack();
});

test('expired totp challenge does not consume a recovery code', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);
    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));

    $recoveryCode = $staff->fresh()->recoveryCodes()[0];
    app(TwoFactorAppChallengeService::class)->begin($staff->staff_id);

    $this->travel(361)->seconds();

    $response = $this->withSession([
        '2fa_app.staff_id' => $staff->staff_id,
        'url.intended' => '/scp',
    ])->post('/scp/2fa-app', [
        'code' => $recoveryCode,
    ]);

    $response->assertRedirect('/scp/login');
    $response->assertSessionHasErrors([
        'general' => 'Too many attempts or code expired. Please log in again.',
    ]);

    expect($staff->fresh()->recoveryCodes())->toContain($recoveryCode);

    $this->travelBack();
});

test('recovery code is not consumed when challenge expires during verification', function () {
    $staff = createLegacyStaff();
    $service = app(StaffTwoFactorService::class);
    $service->enable($staff);
    $service->confirm($staff->fresh(), app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret));

    $recoveryCode = $staff->fresh()->recoveryCodes()[0];

    $challenge = Mockery::mock(TwoFactorAppChallengeService::class);
    $challenge->shouldReceive('hasActiveChallenge')
        ->once()
        ->with($staff->staff_id)
        ->andReturnTrue();
    $challenge->shouldReceive('validateAttempt')
        ->once()
        ->with($staff->staff_id, true)
        ->andReturn(TwoFactorAppChallengeService::STATUS_EXPIRED);

    $this->instance(TwoFactorAppChallengeService::class, $challenge);

    $response = $this->withSession([
        '2fa_app.staff_id' => $staff->staff_id,
        'url.intended' => '/scp',
    ])->post('/scp/2fa-app', [
        'code' => $recoveryCode,
    ]);

    $response->assertRedirect('/scp/login');
    $response->assertSessionHasErrors([
        'general' => 'Too many attempts or code expired. Please log in again.',
    ]);

    expect($staff->fresh()->recoveryCodes())->toContain($recoveryCode);
});

test('non-enrolled staff continue to the email otp flow', function () {
    Mail::fake();

    $staff = createLegacyStaff();

    $response = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $response->assertRedirect('/scp/2fa');
    $response->assertSessionHas('2fa.staff_id', $staff->staff_id);
    expect(app(TwoFactorAuthService::class)->hasPendingToken($staff->staff_id))->toBeTrue();
});
