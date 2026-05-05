<?php

use App\Models\LegacyPermission;
use App\Models\LegacyRole;
use App\Models\Staff;
use App\Models\StaffDeptAccess;
use App\Models\Ticket;
use App\Services\Admin\DepartmentRoleResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    if (! $schema->hasColumn('staff', 'role_id')) {
        $schema->table('staff', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable()->after('dept_id');
        });
    }

    if (! $schema->hasTable('ticket')) {
        $schema->create('ticket', function (Blueprint $table): void {
            $table->unsignedInteger('ticket_id')->primary();
            $table->string('number')->default('');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('status_id')->default(1);
            $table->unsignedInteger('dept_id')->default(0);
            $table->unsignedInteger('staff_id')->default(0);
            $table->unsignedInteger('sla_id')->default(0);
            $table->unsignedInteger('email_id')->default(0);
            $table->string('source')->default('web');
            $table->string('ip_address')->default('127.0.0.1');
            $table->tinyInteger('isoverdue')->default(0);
            $table->tinyInteger('isanswered')->default(0);
            $table->dateTime('duedate')->nullable();
            $table->dateTime('closed')->nullable();
            $table->dateTime('lastupdate')->nullable();
            $table->dateTime('lastmessage')->nullable();
            $table->dateTime('lastresponse')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (Schema::connection('legacy')->hasTable('ticket')) {
        Schema::connection('legacy')->drop('ticket');
    }
});

it('returns the department override role when available', function (): void {
    $primaryRole = LegacyRole::create(['name' => 'Primary', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::create(['name' => 'Override', 'guard_name' => 'staff']);
    $staff = Staff::factory()->create(['dept_id' => 1, 'role_id' => $primaryRole->id]);

    $staff->syncRoles([$primaryRole]);

    $staff->setConnection('legacy')->newQuery()->whereKey($staff->getKey())->update(['role_id' => $primaryRole->id]);

    $staff->unsetRelation('roles');

    StaffDeptAccess::query()->create([
        'staff_id' => $staff->staff_id,
        'dept_id' => 2,
        'role_id' => $overrideRole->id,
        'flags' => 0,
    ]);

    $resolvedRole = app(DepartmentRoleResolver::class)->roleForDepartment($staff->fresh(), 2);

    expect($resolvedRole?->is($overrideRole))->toBeTrue();
});

it('falls back to the primary role when no department override exists or the override role was deleted', function (): void {
    $primaryRole = LegacyRole::create(['name' => 'Primary Fallback', 'guard_name' => 'staff']);
    $deletedRole = LegacyRole::create(['name' => 'Deleted Override', 'guard_name' => 'staff']);
    $staff = Staff::factory()->create(['dept_id' => 1, 'role_id' => $primaryRole->id]);

    $staff->syncRoles([$primaryRole]);

    StaffDeptAccess::query()->create([
        'staff_id' => $staff->staff_id,
        'dept_id' => 2,
        'role_id' => $deletedRole->id,
        'flags' => 0,
    ]);

    $deletedRole->delete();

    $resolver = app(DepartmentRoleResolver::class);

    expect($resolver->roleForDepartment($staff->fresh(), 1)?->is($primaryRole))->toBeTrue()
        ->and($resolver->roleForDepartment($staff->fresh(), 2)?->is($primaryRole))->toBeTrue();
});

it('checks department permissions through the custom gate and staff trait helpers', function (): void {
    $primaryRole = LegacyRole::create(['name' => 'Primary Permission Role', 'guard_name' => 'staff']);
    $overrideRole = LegacyRole::create(['name' => 'Override Permission Role', 'guard_name' => 'staff']);
    $primaryPermission = LegacyPermission::create(['name' => 'admin.role.view', 'guard_name' => 'staff']);
    $overridePermission = LegacyPermission::create(['name' => 'admin.role.update', 'guard_name' => 'staff']);

    $primaryRole->givePermissionTo($primaryPermission);
    $overrideRole->givePermissionTo($overridePermission);

    $staff = Staff::factory()->create(['dept_id' => 1, 'role_id' => $primaryRole->id]);
    $staff->syncRoles([$primaryRole]);

    StaffDeptAccess::query()->create([
        'staff_id' => $staff->staff_id,
        'dept_id' => 2,
        'role_id' => $overrideRole->id,
        'flags' => 0,
    ]);

    $ticket = new Ticket;
    $ticket->dept_id = 2;

    $staff = $staff->fresh();

    expect(Gate::forUser($staff)->check('can-in-department', ['admin.role.view', 1]))->toBeTrue()
        ->and(Gate::forUser($staff)->check('can-in-department', ['admin.role.update', 2]))->toBeTrue()
        ->and(Gate::forUser($staff)->check('can-in-department', ['admin.role.update', 1]))->toBeFalse()
        ->and($staff->canInDept('admin.role.update', 2))->toBeTrue()
        ->and($staff->canForTicket('admin.role.update', $ticket))->toBeTrue()
        ->and($staff->canInDept('admin.role.update', 3))->toBeFalse();
});
