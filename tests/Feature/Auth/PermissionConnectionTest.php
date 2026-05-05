<?php

use App\Models\LegacyPermission;
use App\Models\LegacyRole;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

test('staff permissions relation lazy loads despite legacy permissions column', function () {
    $schema = Schema::connection('legacy');

    if (! $schema->hasColumn('staff', 'permissions')) {
        $schema->table('staff', function (Blueprint $table): void {
            $table->text('permissions')->nullable();
        });
    }

    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => 78,
        'dept_id' => 1,
        'username' => 'legacy-permission-user',
        'firstname' => 'Legacy',
        'lastname' => 'Permission',
        'email' => 'legacy-permission-user@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'permissions' => '{"legacy":true}',
        'created' => now(),
    ]);

    $permission = LegacyPermission::create([
        'name' => 'ticket.create',
        'guard_name' => 'staff',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = Staff::findOrFail(78);
    $staff->givePermissionTo($permission);

    $fresh = Staff::findOrFail(78);

    expect($fresh->relationLoaded('permissions'))->toBeFalse()
        ->and($fresh->permissions->pluck('name')->all())->toBe(['ticket.create'])
        ->and($fresh->relationLoaded('permissions'))->toBeTrue();
});
