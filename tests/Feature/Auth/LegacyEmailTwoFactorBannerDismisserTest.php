<?php

use App\Models\Staff;
use App\Models\StaffAuthMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

function createLegacyEmailTwoFactorStaff(array $attributes = []): Staff
{
    $staffId = $attributes['staff_id'] ?? random_int(1000, 9999);

    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => $staffId,
        'dept_id' => 1,
        'username' => "email2fa{$staffId}",
        'firstname' => 'Email',
        'lastname' => 'Tester',
        'email' => "email2fa{$staffId}@example.com",
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->findOrFail($staffId);
}

function insertLegacyEmailTwoFactorConfig(Staff $staff, int $verified): void
{
    DB::connection('legacy')->table('config')->insert([
        'namespace' => "staff.{$staff->staff_id}",
        'key' => 'email',
        'value' => json_encode([
            'verified' => $verified,
            'config' => [
                'email' => $staff->email,
            ],
        ]),
        'updated' => now(),
    ]);
}

function loginLegacyEmailTwoFactorStaff(Staff $staff): void
{
    test()->post('/scp/login', [
        'username' => $staff->username,
        'password' => 'password',
    ])->assertRedirect('/scp/2fa');
}

test('verified legacy email two-factor dismisses the migration banner', function () {
    $staff = createLegacyEmailTwoFactorStaff();

    insertLegacyEmailTwoFactorConfig($staff, 1700000000);

    loginLegacyEmailTwoFactorStaff($staff);

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($migration)->not->toBeNull()
        ->and($migration?->migrated_at)->not->toBeNull()
        ->and($migration?->dismissed_migration_banner_at)->not->toBeNull()
        ->and($migration?->upgrade_method)->toBeNull();
});

test('unverified legacy email two-factor does not dismiss the migration banner', function () {
    $staff = createLegacyEmailTwoFactorStaff();

    insertLegacyEmailTwoFactorConfig($staff, 0);

    loginLegacyEmailTwoFactorStaff($staff);

    expect(StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first())->toBeNull();
});

test('missing legacy email two-factor config does not dismiss the migration banner', function () {
    $staff = createLegacyEmailTwoFactorStaff();

    loginLegacyEmailTwoFactorStaff($staff);

    expect(StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first())->toBeNull();
});

test('already dismissed legacy email two-factor migration is not overwritten', function () {
    $staff = createLegacyEmailTwoFactorStaff();
    $originalTimestamp = now()->setDate(2024, 1, 1)->setTime(0, 0);

    insertLegacyEmailTwoFactorConfig($staff, 1700000000);

    StaffAuthMigration::query()->create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => $originalTimestamp,
        'dismissed_migration_banner_at' => $originalTimestamp,
    ]);

    $this->travel(1)->day();

    loginLegacyEmailTwoFactorStaff($staff);

    $migration = StaffAuthMigration::query()->where('staff_id', $staff->staff_id)->first();

    expect($migration?->migrated_at?->equalTo($originalTimestamp))->toBeTrue()
        ->and($migration?->dismissed_migration_banner_at?->equalTo($originalTimestamp))->toBeTrue()
        ->and($migration?->upgrade_method)->toBeNull();

    $this->travelBack();
});
