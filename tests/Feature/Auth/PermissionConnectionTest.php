<?php

use App\Models\LegacyRole;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

test('staff roles and permissions stay on the legacy connection', function () {
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 77,
        'dept_id' => 1,
        'username' => 'legacy-role-user',
        'firstname' => 'Legacy',
        'lastname' => 'Role',
        'email' => 'legacy-role-user@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);

    $role = LegacyRole::create([
        'name' => 'department-manager',
        'guard_name' => 'staff',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = Staff::findOrFail(77);
    $staff->assignRole($role);

    expect($staff->roles->pluck('name')->all())->toBe(['department-manager'])
        ->and($staff->hasRole('department-manager'))->toBeTrue()
        ->and($role->getConnectionName())->toBe('legacy')
        ->and(config('permission.connection'))->toBe('legacy');
});
