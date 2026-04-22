<?php

use App\Models\Staff;
use App\Models\StaffAuthMigration;

it('shows the banner for migrated staff who have not dismissed it', function () {
    $staff = Staff::factory()->create(['isactive' => 1]);

    StaffAuthMigration::create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => now()->subDay(),
        'upgrade_method' => 'auto',
    ]);

    $response = $this->actingAs($staff, 'staff')->get('/scp');

    $response->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', true));
});

it('hides the banner once dismissed', function () {
    $staff = Staff::factory()->create(['isactive' => 1]);

    StaffAuthMigration::create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => now()->subDay(),
        'upgrade_method' => 'auto',
        'dismissed_migration_banner_at' => now(),
    ]);

    $response = $this->actingAs($staff, 'staff')->get('/scp');

    $response->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', false));
});

it('persists dismissal via the controller', function () {
    $staff = Staff::factory()->create(['isactive' => 1]);

    StaffAuthMigration::create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => now()->subDay(),
        'upgrade_method' => 'auto',
    ]);

    $this->actingAs($staff, 'staff')
        ->post('/scp/account/migration-banner/dismiss')
        ->assertRedirect();

    expect($staff->fresh()->authMigration?->dismissed_migration_banner_at)->not->toBeNull();
});
