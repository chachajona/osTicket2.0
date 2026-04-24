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

it('caches a false session result for staff who should not see the banner', function () {
    $this->travelTo(now());

    $staff = Staff::factory()->create(['isactive' => 1]);
    $cacheKey = "auth.migration_banner.{$staff->staff_id}";

    $response = $this->actingAs($staff, 'staff')
        ->get('/scp');

    $response
        ->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', false))
        ->assertSessionHas($cacheKey, fn (array $cache): bool => $cache['visible'] === false && is_int($cache['cached_at']));

    $this->travelBack();
});

it('recomputes a stale false session result when migration state changes', function () {
    $this->travelTo(now());

    $staff = Staff::factory()->create(['isactive' => 1]);
    $cacheKey = "auth.migration_banner.{$staff->staff_id}";

    $this->actingAs($staff, 'staff')
        ->get('/scp')
        ->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', false))
        ->assertSessionHas($cacheKey, fn (array $cache): bool => $cache['visible'] === false && is_int($cache['cached_at']));

    StaffAuthMigration::create([
        'staff_id' => $staff->staff_id,
        'migrated_at' => now()->subDay(),
        'upgrade_method' => 'auto',
    ]);

    $this->travel(6)->minutes();

    $this->actingAs($staff->fresh(), 'staff')
        ->get('/scp')
        ->assertInertia(fn ($page) => $page->where('auth.staff.migrationBanner', true))
        ->assertSessionHas($cacheKey, fn (array $cache): bool => $cache['visible'] === true && is_int($cache['cached_at']));

    $this->travelBack();
});
