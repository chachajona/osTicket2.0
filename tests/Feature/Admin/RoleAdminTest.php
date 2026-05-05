<?php

declare(strict_types=1);

use App\Models\LegacyRole;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('role')) {
        $schema->create('role', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('flags')->default(0);
            $table->string('name', 64)->unique();
            $table->text('permissions')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    Role::query()->delete();
    LegacyRole::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantAdminPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function roleAuditPayload(Role $role, array $permissions, ?string $name = null, ?string $notes = null): array
{
    return [
        'id' => $role->id,
        'name' => $name ?? $role->name,
        'notes' => $notes,
        'flags' => 1,
        'permissions' => $permissions,
    ];
}

it('renders the role index for authorized admins with permission counts', function (): void {
    Role::query()->create([
        'flags' => 1,
        'name' => 'Operations',
        'permissions' => json_encode(['admin.role.create', 'admin.role.update']),
        'notes' => 'Ops team',
        'created' => now(),
        'updated' => now(),
    ]);

    Role::query()->create([
        'flags' => 1,
        'name' => 'Read Only',
        'permissions' => json_encode(['admin.role.delete']),
        'notes' => 'Limited',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.update']);

    actingAs($staff, 'staff');

    get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Roles/Index')
            ->has('roles.data', 2)
            ->where('roles.data.0.name', 'Operations')
            ->where('roles.data.0.permissions_count', 2)
            ->where('roles.data.1.name', 'Read Only')
            ->where('roles.data.1.permissions_count', 1)
        );
});

it('forbids the role index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.roles.index'))->assertForbidden();
});

it('renders create and edit pages with grouped permissions for authorized admins', function (): void {
    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.create', 'admin.role.update']);
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode(['admin.role.create', 'admin.role.update']),
        'notes' => 'Managers role',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAs($staff, 'staff');

    get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Roles/Edit')
            ->where('role', null)
            ->has('permissions', 7)
            ->where('permissions.6.id', 'admin')
            ->where('permissions.6.name', 'Admin')
            ->where('permissions.6.permissions.0.id', 'admin.access')
            ->where('selectedPermissions', [])
        );

    actingAs($staff, 'staff');

    get(route('admin.roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Roles/Edit')
            ->where('role.name', 'Managers')
            ->where('selectedPermissions', ['admin.role.create', 'admin.role.update'])
            ->where('permissions.6.id', 'admin')
        );
});

it('creates a role, syncs spatie permissions, and writes an audit log', function (): void {
    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.create']);

    actingAs($staff, 'staff');

    post(route('admin.roles.store'), [
        'name' => 'Operations',
        'notes' => 'Ops team',
        'permissions' => ['admin.role.update', 'admin.role.create'],
    ])->assertRedirect();

    $role = Role::query()->where('name', 'Operations')->firstOrFail();
    $spatieRole = LegacyRole::query()->where('name', 'Operations')->firstOrFail();

    expect(json_decode((string) $role->permissions, true))->toBe(['admin.role.create', 'admin.role.update'])
        ->and($spatieRole->id)->toBe($role->id)
        ->and($spatieRole->permissions->pluck('name')->sort()->values()->all())->toBe(['admin.role.create', 'admin.role.update']);

    assertDatabaseHas('role', ['name' => 'Operations', 'notes' => 'Ops team'], 'legacy');

    assertAuditLogged('role.create', $role, null, roleAuditPayload($role, ['admin.role.create', 'admin.role.update'], 'Operations', 'Ops team'));
});

it('rejects invalid role creation payloads', function (): void {
    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.create']);

    Role::query()->create([
        'flags' => 1,
        'name' => 'Existing',
        'permissions' => json_encode(['admin.role.create']),
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAs($staff, 'staff');

    from(route('admin.roles.create'))
        ->post(route('admin.roles.store'), [
            'name' => 'Existing',
            'notes' => str_repeat('a', 256),
            'permissions' => ['missing.permission'],
        ])
        ->assertSessionHasErrors(['name', 'notes', 'permissions.0']);

    expect(Role::query()->count())->toBe(1);
});

it('forbids unauthorized role creation', function (): void {
    actingAsAgent();

    post(route('admin.roles.store'), [
        'name' => 'Operations',
        'permissions' => ['admin.role.create'],
    ])->assertForbidden();
});

it('updates a role, syncs spatie permissions, and writes an audit log diff', function (): void {
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode(['admin.role.create']),
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);

    $spatieRole = LegacyRole::query()->create([
        'name' => 'Managers',
        'guard_name' => 'staff',
    ]);
    seedPermissions();
    $spatieRole->syncPermissions(['admin.role.create']);

    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.update']);

    actingAs($staff, 'staff');

    put(route('admin.roles.update', $role), [
        'name' => 'Supervisors',
        'notes' => 'Updated notes',
        'permissions' => ['admin.role.delete', 'admin.role.update'],
    ])->assertRedirect(route('admin.roles.edit', $role));

    $role->refresh();
    $spatieRole->refresh();

    expect($role->name)->toBe('Supervisors')
        ->and($role->notes)->toBe('Updated notes')
        ->and(json_decode((string) $role->permissions, true))->toBe(['admin.role.delete', 'admin.role.update'])
        ->and($spatieRole->name)->toBe('Supervisors')
        ->and($spatieRole->permissions->pluck('name')->sort()->values()->all())->toBe(['admin.role.delete', 'admin.role.update']);

    assertAuditLogged(
        'role.update',
        $role,
        roleAuditPayload($role, ['admin.role.create'], 'Managers', 'Old notes'),
        roleAuditPayload($role, ['admin.role.delete', 'admin.role.update'], 'Supervisors', 'Updated notes'),
    );
});

it('rejects invalid role updates', function (): void {
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode(['admin.role.create']),
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);

    Role::query()->create([
        'flags' => 1,
        'name' => 'Existing',
        'permissions' => json_encode([]),
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.update']);

    actingAs($staff, 'staff');

    from(route('admin.roles.edit', $role))
        ->put(route('admin.roles.update', $role), [
            'name' => 'Existing',
            'notes' => str_repeat('b', 256),
            'permissions' => ['missing.permission'],
        ])
        ->assertSessionHasErrors(['name', 'notes', 'permissions.0']);

    expect($role->fresh()->name)->toBe('Managers');
});

it('forbids unauthorized role updates', function (): void {
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode([]),
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAsAgent();

    put(route('admin.roles.update', $role), [
        'name' => 'Supervisors',
        'permissions' => ['admin.role.update'],
    ])->assertForbidden();
});

it('deletes a role and writes an audit log entry', function (): void {
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode(['admin.role.update']),
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);

    $spatieRole = LegacyRole::query()->create([
        'name' => 'Managers',
        'guard_name' => 'staff',
    ]);
    seedPermissions();
    $spatieRole->syncPermissions(['admin.role.update']);

    $staff = grantAdminPermissions(actingAsAdmin(), ['admin.role.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    assertDatabaseMissing('role', ['id' => $role->id], 'legacy');
    assertDatabaseMissing('roles', ['name' => 'Managers'], 'legacy');

    assertAuditLogged('role.delete', $role, roleAuditPayload($role, ['admin.role.update'], 'Managers', 'Old notes'), null);
});

it('forbids unauthorized role deletion', function (): void {
    $role = Role::query()->create([
        'flags' => 1,
        'name' => 'Managers',
        'permissions' => json_encode([]),
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAsAgent();

    delete(route('admin.roles.destroy', $role))->assertForbidden();
});
