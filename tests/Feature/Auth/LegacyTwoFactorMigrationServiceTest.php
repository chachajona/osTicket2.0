<?php

use App\Models\Staff;
use App\Models\StaffAuthMigration;
use App\Models\StaffTwoFactorCredential;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('config')) {
        $schema->create('config', function (Blueprint $table): void {
            $table->id();
            $table->string('namespace');
            $table->string('key');
            $table->text('value');
            $table->timestamp('updated')->nullable();
        });
    }

    DB::connection('legacy')->table('config')->delete();
});

function createLegacyMigrationStaff(array $attributes = []): Staff
{
    $staffId = $attributes['staff_id'] ?? random_int(1000, 9999);

    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => $staffId,
        'dept_id' => 1,
        'username' => "migration{$staffId}",
        'firstname' => 'Migration',
        'lastname' => 'Tester',
        'email' => "migration{$staffId}@example.com",
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->findOrFail($staffId);
}

function insertLegacyTwoFactorConfig(Staff $staff, string $backend, array $config, int $verified): void
{
    DB::connection('legacy')->table('config')->insert([
        'namespace' => "staff.{$staff->staff_id}",
        'key' => $backend,
        'value' => json_encode([
            'config' => $config,
            'verified' => $verified,
        ]),
        'updated' => now(),
    ]);
}

function loginLegacyMigrationStaff(Staff $staff, string $redirect = '/scp/2fa'): void
{
    Mail::fake();

    test()->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ])->assertRedirect($redirect);
}

test('verified legacy totp plugin imports app credentials and marks totp migration', function () {
    $staff = createLegacyMigrationStaff();

    insertLegacyTwoFactorConfig($staff, 'auth.agent', [
        'key' => 'JBSWY3DPEHPK3PXP',
        'external2fa' => true,
    ], 1700000000);

    loginLegacyMigrationStaff($staff, '/scp/2fa-app');

    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();
    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential)->not->toBeNull()
        ->and($credential?->two_factor_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($credential?->two_factor_recovery_codes)->toHaveCount(8)
        ->and($credential?->two_factor_confirmed_at)->not->toBeNull()
        ->and($migration)->not->toBeNull()
        ->and($migration?->migrated_at)->not->toBeNull()
        ->and($migration?->upgrade_method)->toBe('totp');
});

test('unverified legacy totp plugin does not migrate anything', function () {
    $staff = createLegacyMigrationStaff();

    insertLegacyTwoFactorConfig($staff, 'auth.agent', [
        'key' => 'JBSWY3DPEHPK3PXP',
        'external2fa' => true,
    ], 0);

    loginLegacyMigrationStaff($staff);

    expect(StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse()
        ->and(StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse();
});

test('verified legacy email otp dismisses the migration banner without creating app credentials', function () {
    $staff = createLegacyMigrationStaff();

    insertLegacyTwoFactorConfig($staff, 'email', [
        'email' => 'a@b.com',
    ], 1700000000);

    loginLegacyMigrationStaff($staff);

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect(StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse()
        ->and($migration)->not->toBeNull()
        ->and($migration?->migrated_at)->not->toBeNull()
        ->and($migration?->dismissed_migration_banner_at)->not->toBeNull()
        ->and($migration?->upgrade_method)->toBeNull();
});

test('missing legacy two-factor config does not migrate anything', function () {
    $staff = createLegacyMigrationStaff();

    loginLegacyMigrationStaff($staff);

    expect(StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse()
        ->and(StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->exists())->toBeFalse();
});

test('already migrated totp credentials are not overwritten', function () {
    $this->travelTo(now());

    $staff = createLegacyMigrationStaff();
    $staff->upsertTwoFactorCredential([
        'two_factor_secret' => 'EXISTINGSECRET',
        'two_factor_recovery_codes' => ['existing-code'],
        'two_factor_confirmed_at' => now(),
    ]);
    $originalConfirmedAt = StaffTwoFactorCredential::query()
        ->where('staff_id', $staff->staff_id)
        ->firstOrFail()
        ->two_factor_confirmed_at;

    insertLegacyTwoFactorConfig($staff, 'auth.agent', [
        'key' => 'JBSWY3DPEHPK3PXP',
        'external2fa' => true,
    ], 1700000000);

    $this->travel(1)->day();

    loginLegacyMigrationStaff($staff, '/scp/2fa-app');

    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential?->two_factor_secret)->toBe('EXISTINGSECRET')
        ->and($credential?->two_factor_confirmed_at?->equalTo($originalConfirmedAt))->toBeTrue();

    $this->travelBack();
});

test('disabled dual legacy totp and email migration is not reimported or shown as a banner', function () {
    $staff = createLegacyMigrationStaff();

    $staff->upsertTwoFactorCredential([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);
    StaffAuthMigration::query()->create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => null,
        'upgrade_method' => null,
        'dismissed_migration_banner_at' => null,
    ]);
    insertLegacyTwoFactorConfig($staff, 'auth.agent', [
        'key' => 'JBSWY3DPEHPK3PXP',
        'external2fa' => true,
    ], 1700000000);
    insertLegacyTwoFactorConfig($staff, 'email', [
        'email' => 'a@b.com',
    ], 1700000000);

    loginLegacyMigrationStaff($staff);

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();
    $credential = StaffTwoFactorCredential::query()->where('staff_id', $staff->staff_id)->first();

    expect($credential)->not->toBeNull()
        ->and($credential?->two_factor_secret)->toBeNull()
        ->and($credential?->two_factor_confirmed_at)->toBeNull()
        ->and($migration?->migrated_at)->toBeNull()
        ->and($migration?->upgrade_method)->toBeNull()
        ->and($migration?->dismissed_migration_banner_at)->toBeNull();

    $this->actingAs($staff->fresh(), 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp')
        ->assertOk()
        ->assertJsonPath('props.auth.staff.migrationBanner', false);
});

test('already dismissed email migration is not overwritten', function () {
    $this->travelTo(now()->setDate(2024, 1, 1)->setTime(0, 0));

    $staff = createLegacyMigrationStaff();
    $originalTimestamp = now();

    StaffAuthMigration::query()->create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => $originalTimestamp,
        'dismissed_migration_banner_at' => $originalTimestamp,
    ]);
    insertLegacyTwoFactorConfig($staff, 'email', [
        'email' => 'a@b.com',
    ], 1700000000);

    $this->travel(1)->day();

    loginLegacyMigrationStaff($staff);

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($migration?->migrated_at?->equalTo($originalTimestamp))->toBeTrue()
        ->and($migration?->dismissed_migration_banner_at?->equalTo($originalTimestamp))->toBeTrue()
        ->and($migration?->upgrade_method)->toBeNull();

    $this->travelBack();
});
