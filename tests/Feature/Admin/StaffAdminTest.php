<?php

declare(strict_types=1);

use App\Models\Admin\AdminAuditLog;
use App\Models\Department;
use App\Models\LegacyRole;
use App\Models\Staff;
use App\Models\StaffDeptAccess;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
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

    if (! $schema->hasTable('department')) {
        $schema->create('department', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 128);
            $table->unsignedInteger('dept_id')->nullable();
            $table->unsignedInteger('manager_id')->nullable();
            $table->unsignedInteger('sla_id')->nullable();
            $table->unsignedInteger('email_id')->nullable();
            $table->unsignedInteger('tpl_id')->nullable();
            $table->text('signature')->nullable();
            $table->tinyInteger('ispublic')->default(1);
        });
    }

    foreach ([
        'role_id' => fn (Blueprint $table) => $table->unsignedBigInteger('role_id')->nullable()->after('dept_id'),
        'phone' => fn (Blueprint $table) => $table->string('phone', 32)->nullable()->after('email'),
        'mobile' => fn (Blueprint $table) => $table->string('mobile', 32)->nullable()->after('phone'),
        'signature' => fn (Blueprint $table) => $table->text('signature')->nullable()->after('mobile'),
        'isvisible' => fn (Blueprint $table) => $table->tinyInteger('isvisible')->default(1)->after('isadmin'),
        'change_passwd' => fn (Blueprint $table) => $table->tinyInteger('change_passwd')->default(0)->after('isvisible'),
        'passwdreset' => fn (Blueprint $table) => $table->timestamp('passwdreset')->nullable()->after('lastlogin'),
        'updated' => fn (Blueprint $table) => $table->timestamp('updated')->nullable()->after('created'),
    ] as $column => $definition) {
        if (! $schema->hasColumn('staff', $column)) {
            $schema->table('staff', $definition);
        }
    }

    if (! $schema->hasTable('team')) {
        $schema->create('team', function (Blueprint $table): void {
            $table->increments('team_id');
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('flags')->default(1);
            $table->string('name', 64);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('team_member')) {
        $schema->create('team_member', function (Blueprint $table): void {
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('flags')->default(0);
            $table->primary(['team_id', 'staff_id']);
        });
    }

    Department::query()->delete();
    StaffDeptAccess::query()->delete();
    TeamMember::query()->delete();
    Team::query()->delete();
    Staff::query()->delete();
    LegacyRole::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantStaffPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function staffAuditPayload(Staff $staff, array $deptAccess, array $teams): array
{
    usort($deptAccess, static fn (array $left, array $right): int => $left['dept_id'] <=> $right['dept_id']);
    sort($teams);

    return [
        'staff_id' => $staff->staff_id,
        'username' => $staff->username,
        'firstname' => $staff->firstname,
        'lastname' => $staff->lastname,
        'email' => $staff->email,
        'phone' => $staff->phone !== '' ? $staff->phone : null,
        'mobile' => $staff->mobile !== '' ? $staff->mobile : null,
        'signature' => $staff->signature !== '' ? $staff->signature : null,
        'dept_id' => (int) $staff->dept_id,
        'department_name' => Department::query()->find($staff->dept_id)?->name,
        'role_id' => $staff->role_id !== null ? (int) $staff->role_id : null,
        'role_name' => LegacyRole::query()->find($staff->role_id)?->name,
        'isactive' => (bool) ($staff->isactive ?? 0),
        'isadmin' => (bool) ($staff->isadmin ?? 0),
        'isvisible' => (bool) ($staff->isvisible ?? 0),
        'change_passwd' => (bool) ($staff->change_passwd ?? 0),
        'password' => '[redacted]',
        'dept_access' => $deptAccess,
        'teams' => $teams,
        'two_factor_enabled' => false,
        'two_factor_confirmed_at' => null,
    ];
}

it('renders the staff index for authorized admins', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);

    Staff::factory()->create([
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'username' => 'asmith',
        'firstname' => 'Alex',
        'lastname' => 'Smith',
        'email' => 'alex@example.com',
        'isactive' => 1,
    ]);

    Staff::factory()->create([
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'username' => 'bdoe',
        'firstname' => 'Bailey',
        'lastname' => 'Doe',
        'email' => 'bailey@example.com',
        'isactive' => 0,
    ]);

    $staff = grantStaffPermissions(actingAsAdmin(Staff::factory()->create([
        'username' => 'zz-admin',
        'firstname' => 'Zed',
        'lastname' => 'Admin',
        'email' => 'zed-admin@example.com',
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'isactive' => 1,
    ])), ['admin.staff.update']);

    actingAs($staff, 'staff');

    get(route('admin.staff.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Staff/Index')
            ->has('staff.data', 3)
            ->where('staff.data.0.username', 'asmith')
            ->where('staff.data.0.department', 'Support')
            ->where('staff.data.0.role', 'Managers')
            ->where('staff.data.0.isactive', true)
            ->where('staff.data.1.username', 'bdoe')
            ->where('staff.data.1.isactive', false)
        );
});

it('forbids the staff index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.staff.index'))->assertForbidden();
});

it('renders create and edit pages with options and relationship selections', function (): void {
    $primaryDepartment = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $secondaryDepartment = Department::query()->create(['name' => 'Billing', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::query()->create(['name' => 'Escalations', 'guard_name' => 'staff']);
    $team = Team::query()->create(['name' => 'Escalations', 'flags' => 1, 'created' => now(), 'updated' => now()]);

    $member = Staff::factory()->create([
        'dept_id' => $primaryDepartment->id,
        'role_id' => $role->id,
        'username' => 'agent01',
    ]);

    StaffDeptAccess::query()->create([
        'staff_id' => $member->staff_id,
        'dept_id' => $secondaryDepartment->id,
        'role_id' => $overrideRole->id,
        'flags' => 0,
    ]);

    TeamMember::query()->create([
        'team_id' => $team->team_id,
        'staff_id' => $member->staff_id,
        'flags' => 0,
    ]);

    $staff = grantStaffPermissions(actingAsAdmin(), ['admin.staff.create', 'admin.staff.update']);

    actingAs($staff, 'staff');

    get(route('admin.staff.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Staff/Edit')
            ->where('staffMember', null)
            ->has('departmentOptions', 2)
            ->has('roleOptions', 2)
            ->has('teamOptions', 1)
        );

    actingAs($staff, 'staff');

    get(route('admin.staff.edit', $member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Staff/Edit')
            ->where('staffMember.username', 'agent01')
            ->where('staffMember.dept_access', [['dept_id' => $secondaryDepartment->id, 'role_id' => $overrideRole->id]])
            ->where('staffMember.teams', [$team->team_id])
            ->where('staffMember.two_factor.enabled', false)
        );
});

it('creates a staff member, hashes the password, syncs relationships, and writes an audit log', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $secondaryDepartment = Department::query()->create(['name' => 'Billing', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::query()->create(['name' => 'Escalations', 'guard_name' => 'staff']);
    $teamOne = Team::query()->create(['name' => 'Escalations', 'flags' => 1, 'created' => now(), 'updated' => now()]);
    $teamTwo = Team::query()->create(['name' => 'Weekend', 'flags' => 1, 'created' => now(), 'updated' => now()]);

    $staff = grantStaffPermissions(actingAsAdmin(), ['admin.staff.create']);

    actingAs($staff, 'staff');

    post(route('admin.staff.store'), [
        'username' => 'jrivera',
        'firstname' => 'Jamie',
        'lastname' => 'Rivera',
        'email' => 'jamie@example.com',
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'password' => 'secret-pass-1',
        'phone' => '555-0101',
        'mobile' => '555-0102',
        'signature' => 'Thanks, Jamie',
        'isactive' => true,
        'isadmin' => true,
        'isvisible' => true,
        'change_passwd' => true,
        'dept_access' => [
            ['dept_id' => $secondaryDepartment->id, 'role_id' => $overrideRole->id],
        ],
        'teams' => [$teamTwo->team_id, $teamOne->team_id],
    ])->assertRedirect();

    $member = Staff::query()->where('username', 'jrivera')->firstOrFail();
    $member->load(['department', 'role', 'departmentAccesses', 'teams']);

    expect(Hash::check('secret-pass-1', $member->passwd))->toBeTrue()
        ->and($member->passwd)->not->toBe('secret-pass-1')
        ->and($member->roles->pluck('id')->all())->toBe([$role->id]);

    assertDatabaseHas('staff', [
        'staff_id' => $member->staff_id,
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'isactive' => 1,
        'isadmin' => 1,
        'isvisible' => 1,
        'change_passwd' => 1,
    ], 'legacy');

    assertDatabaseHas('staff_dept_access', [
        'staff_id' => $member->staff_id,
        'dept_id' => $secondaryDepartment->id,
        'role_id' => $overrideRole->id,
    ], 'legacy');

    assertDatabaseHas('team_member', ['team_id' => $teamOne->team_id, 'staff_id' => $member->staff_id], 'legacy');
    assertDatabaseHas('team_member', ['team_id' => $teamTwo->team_id, 'staff_id' => $member->staff_id], 'legacy');

    $audit = assertAuditLogged(
        'staff.create',
        $member,
        null,
        staffAuditPayload(
            $member,
            [['dept_id' => $secondaryDepartment->id, 'role_id' => $overrideRole->id]],
            [$teamOne->team_id, $teamTwo->team_id],
        ),
    );

    expect($audit->after['password'])->toBe('[redacted]');
});

it('rejects invalid staff creation payloads', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $team = Team::query()->create(['name' => 'Escalations', 'flags' => 1, 'created' => now(), 'updated' => now()]);

    Staff::factory()->create(['username' => 'taken-user']);
    $staff = grantStaffPermissions(actingAsAdmin(), ['admin.staff.create']);

    actingAs($staff, 'staff');

    from(route('admin.staff.create'))
        ->post(route('admin.staff.store'), [
            'username' => 'taken-user',
            'firstname' => '',
            'lastname' => '',
            'email' => 'invalid-email',
            'dept_id' => 9999,
            'role_id' => 9999,
            'password' => 'short',
            'isactive' => 'bad',
            'isadmin' => 'bad',
            'isvisible' => 'bad',
            'dept_access' => [
                ['dept_id' => $department->id, 'role_id' => 'bad'],
            ],
            'teams' => [$team->team_id, 'bad'],
        ])
        ->assertSessionHasErrors([
            'username',
            'firstname',
            'lastname',
            'email',
            'dept_id',
            'role_id',
            'password',
            'isactive',
            'isadmin',
            'isvisible',
            'dept_access.0.role_id',
            'teams.1',
        ]);
});

it('forbids unauthorized staff creation', function (): void {
    actingAsAgent();

    post(route('admin.staff.store'), [
        'username' => 'jrivera',
        'firstname' => 'Jamie',
        'lastname' => 'Rivera',
        'email' => 'jamie@example.com',
        'dept_id' => 1,
        'role_id' => 1,
        'password' => 'secret-pass-1',
        'isactive' => true,
        'isadmin' => false,
        'isvisible' => true,
    ])->assertForbidden();
});

it('updates a staff member, rehashes password, syncs relationships, and writes an audit diff', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $secondaryDepartment = Department::query()->create(['name' => 'Billing', 'ispublic' => 1]);
    $tertiaryDepartment = Department::query()->create(['name' => 'Escalations', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $newRole = LegacyRole::query()->create(['name' => 'Admins', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::query()->create(['name' => 'Escalations', 'guard_name' => 'staff']);
    $newOverrideRole = LegacyRole::query()->create(['name' => 'Billing Lead', 'guard_name' => 'staff']);
    $teamOne = Team::query()->create(['name' => 'Escalations', 'flags' => 1, 'created' => now(), 'updated' => now()]);
    $teamTwo = Team::query()->create(['name' => 'Weekend', 'flags' => 1, 'created' => now(), 'updated' => now()]);

    $member = Staff::factory()->create([
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'username' => 'jrivera',
        'firstname' => 'Jamie',
        'lastname' => 'Rivera',
        'email' => 'jamie@example.com',
        'passwd' => Hash::make('old-password'),
        'phone' => '555-0101',
        'mobile' => '555-0102',
        'signature' => 'Old signature',
        'isactive' => 1,
        'isadmin' => 0,
        'isvisible' => 1,
        'change_passwd' => 0,
        'updated' => now(),
    ]);
    $member->syncRoles([$role]);

    StaffDeptAccess::query()->create([
        'staff_id' => $member->staff_id,
        'dept_id' => $secondaryDepartment->id,
        'role_id' => $overrideRole->id,
        'flags' => 0,
    ]);

    TeamMember::query()->create(['team_id' => $teamOne->team_id, 'staff_id' => $member->staff_id, 'flags' => 0]);

    $before = staffAuditPayload(
        $member->fresh(),
        [['dept_id' => $secondaryDepartment->id, 'role_id' => $overrideRole->id]],
        [$teamOne->team_id],
    );

    $staff = grantStaffPermissions(actingAsAdmin(), ['admin.staff.update']);

    actingAs($staff, 'staff');

    put(route('admin.staff.update', $member), [
        'username' => 'jr-admin',
        'firstname' => 'Jordan',
        'lastname' => 'Rivera',
        'email' => 'jordan@example.com',
        'dept_id' => $tertiaryDepartment->id,
        'role_id' => $newRole->id,
        'password' => 'new-secret-22',
        'phone' => '555-1111',
        'mobile' => '555-2222',
        'signature' => 'Updated signature',
        'isactive' => false,
        'isadmin' => true,
        'isvisible' => false,
        'change_passwd' => true,
        'dept_access' => [
            ['dept_id' => $secondaryDepartment->id, 'role_id' => $newOverrideRole->id],
        ],
        'teams' => [$teamTwo->team_id],
    ])->assertRedirect(route('admin.staff.edit', $member));

    $member->refresh()->load(['department', 'role', 'departmentAccesses', 'teams']);

    expect($member->username)->toBe('jr-admin')
        ->and((int) $member->dept_id)->toBe($tertiaryDepartment->id)
        ->and((int) $member->role_id)->toBe($newRole->id)
        ->and((int) $member->isactive)->toBe(0)
        ->and((int) $member->isadmin)->toBe(1)
        ->and((int) $member->isvisible)->toBe(0)
        ->and((int) $member->change_passwd)->toBe(1)
        ->and(Hash::check('new-secret-22', $member->passwd))->toBeTrue()
        ->and(TeamMember::query()->where('staff_id', $member->staff_id)->pluck('team_id')->all())->toBe([$teamTwo->team_id])
        ->and(StaffDeptAccess::query()->where('staff_id', $member->staff_id)->pluck('role_id', 'dept_id')->all())->toBe([$secondaryDepartment->id => $newOverrideRole->id])
        ->and($member->roles->pluck('id')->all())->toBe([$newRole->id]);

    assertDatabaseMissing('team_member', ['team_id' => $teamOne->team_id, 'staff_id' => $member->staff_id], 'legacy');

    assertAuditLogged(
        'staff.update',
        $member,
        $before,
        staffAuditPayload(
            $member,
            [['dept_id' => $secondaryDepartment->id, 'role_id' => $newOverrideRole->id]],
            [$teamTwo->team_id],
        ),
    );
});

it('deletes a staff member, cascades relationships, and writes an audit log', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $secondaryDepartment = Department::query()->create(['name' => 'Billing', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::query()->create(['name' => 'Escalations', 'guard_name' => 'staff']);
    $team = Team::query()->create(['name' => 'Escalations', 'flags' => 1, 'created' => now(), 'updated' => now()]);

    $member = Staff::factory()->create([
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'username' => 'jrivera',
        'isvisible' => 1,
        'updated' => now(),
    ]);
    $member->syncRoles([$role]);

    StaffDeptAccess::query()->create([
        'staff_id' => $member->staff_id,
        'dept_id' => $secondaryDepartment->id,
        'role_id' => $overrideRole->id,
        'flags' => 0,
    ]);
    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $member->staff_id, 'flags' => 0]);

    $staff = grantStaffPermissions(actingAsAdmin(), ['admin.staff.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.staff.destroy', $member))
        ->assertRedirect(route('admin.staff.index'));

    assertDatabaseMissing('staff', ['staff_id' => $member->staff_id], 'legacy');
    assertDatabaseMissing('staff_dept_access', ['staff_id' => $member->staff_id], 'legacy');
    assertDatabaseMissing('team_member', ['staff_id' => $member->staff_id], 'legacy');

    assertAuditLogged(
        'staff.delete',
        $member,
        staffAuditPayload(
            $member,
            [['dept_id' => $secondaryDepartment->id, 'role_id' => $overrideRole->id]],
            [$team->team_id],
        ),
        null,
    );
});

it('forbids unauthorized updates and deletion', function (): void {
    $department = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $role = LegacyRole::query()->create(['name' => 'Managers', 'guard_name' => 'staff']);
    $member = Staff::factory()->create([
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'updated' => now(),
    ]);

    actingAsAgent();

    put(route('admin.staff.update', $member), [
        'username' => 'blocked',
        'firstname' => 'Blocked',
        'lastname' => 'User',
        'email' => 'blocked@example.com',
        'dept_id' => $department->id,
        'role_id' => $role->id,
        'isactive' => true,
        'isadmin' => false,
        'isvisible' => true,
    ])->assertForbidden();

    delete(route('admin.staff.destroy', $member))->assertForbidden();

    expect(AdminAuditLog::query()->where('subject_type', 'Staff')->count())->toBe(0);
});
