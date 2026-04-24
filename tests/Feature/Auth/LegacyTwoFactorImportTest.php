<?php

use App\Models\Staff;
use App\Models\StaffAuthMigration;
use App\Models\StaffTwoFactorCredential;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;

if (! function_exists('createLegacyStaff')) {
    function createLegacyStaff(array $attributes = []): Staff
    {
        $staffId = $attributes['staff_id'] ?? random_int(1000, 9999);
        $payload = array_merge([
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
        ], $attributes);

        DB::connection('legacy')->table('staff')->insert($payload);

        $staff = new Staff($payload);
        $staff->exists = true;

        return $staff;
    }
}

beforeEach(function () {
    $column = config('auth.legacy_totp_column', 'totp_secret');

    if (! Schema::connection('legacy')->hasColumn('staff', $column)) {
        Schema::connection('legacy')->table('staff', function (Blueprint $table): void {
            $table->string('totp_secret', 128)->nullable()->after('lastlogin');
        });
    }
});

function setLegacyTotpSecret(Staff $staff, ?string $secret): void
{
    DB::connection('legacy')
        ->table('staff')
        ->where('staff_id', $staff->staff_id)
        ->update([
            config('auth.legacy_totp_column', 'totp_secret') => $secret,
        ]);
}

test('legacy two factor import routes staff to the app challenge', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $secret = app(Google2FA::class)->generateSecretKey();

    setLegacyTotpSecret($staff, $secret);

    $response = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $response->assertRedirect('/scp/2fa-app');
    $response->assertSessionHas('2fa_app.staff_id', $staff->staff_id);

    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();
    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential)->not->toBeNull()
        ->and($credential?->two_factor_secret)->toBe($secret)
        ->and($credential?->two_factor_confirmed_at)->not->toBeNull()
        ->and($credential?->two_factor_recovery_codes)->toHaveCount(8)
        ->and($migration)->not->toBeNull()
        ->and($migration?->upgrade_method)->toBe('totp')
        ->and($migration?->migrated_at)->not->toBeNull();

    $this->travelBack();
});

test('legacy two factor import leaves staff on email otp when no secret exists', function () {
    $staff = createLegacyStaff();

    setLegacyTotpSecret($staff, null);

    $response = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $response->assertRedirect('/scp/2fa');
    $response->assertSessionHas('2fa.staff_id', $staff->staff_id);

    expect(StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse();
});

test('legacy two factor import does not overwrite confirmed new credentials', function () {
    $staff = createLegacyStaff();
    $legacySecret = app(Google2FA::class)->generateSecretKey();
    $existingSecret = 'EXISTING-SECRET-KEY';
    $now = now();

    setLegacyTotpSecret($staff, $legacySecret);

    DB::connection('osticket2')->table('staff_two_factor')->updateOrInsert(
        ['staff_id' => $staff->staff_id],
        [
            'two_factor_secret' => Crypt::encryptString($existingSecret),
            'two_factor_recovery_codes' => encrypt(['alpha-beta-gamma']),
            'two_factor_confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    );

    $response = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $response->assertRedirect('/scp/2fa-app');

    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential)->not->toBeNull()
        ->and($credential?->two_factor_secret)->toBe($existingSecret)
        ->and(StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse();
});

test('legacy two factor import is idempotent across repeated logins', function () {
    $this->travelTo(now());

    $staff = createLegacyStaff();
    $secret = app(Google2FA::class)->generateSecretKey();

    setLegacyTotpSecret($staff, $secret);

    $firstResponse = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $firstResponse->assertRedirect('/scp/2fa-app');

    $firstConfirmedAt = StaffTwoFactorCredential::query()
        ->where('staff_id', $staff->staff_id)
        ->firstOrFail()
        ->two_factor_confirmed_at;

    $this->travel(1)->second();

    $secondResponse = $this->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ]);

    $secondResponse->assertRedirect('/scp/2fa-app');

    $secondConfirmedAt = StaffTwoFactorCredential::query()
        ->where('staff_id', $staff->staff_id)
        ->firstOrFail()
        ->two_factor_confirmed_at;

    expect($secondConfirmedAt?->equalTo($firstConfirmedAt))->toBeTrue();

    $this->travelBack();
});
