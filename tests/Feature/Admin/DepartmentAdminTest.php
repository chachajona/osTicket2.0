<?php

declare(strict_types=1);

use App\Models\Admin\AdminAuditLog;
use App\Models\Department;
use App\Models\EmailModel;
use App\Models\EmailTemplateGroup;
use App\Models\Sla;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->unsignedInteger('sla_id')->nullable();
            $table->unsignedInteger('manager_id')->nullable();
            $table->text('signature')->nullable();
            $table->tinyInteger('ispublic')->default(1);
            $table->unsignedInteger('email_id')->nullable();
            $table->unsignedInteger('tpl_id')->nullable();
            $table->unsignedInteger('dept_id')->nullable();
        });
    }

    $missingDepartmentColumns = [
        'sla_id' => fn (Blueprint $table) => $table->unsignedInteger('sla_id')->nullable(),
        'manager_id' => fn (Blueprint $table) => $table->unsignedInteger('manager_id')->nullable(),
        'signature' => fn (Blueprint $table) => $table->text('signature')->nullable(),
        'ispublic' => fn (Blueprint $table) => $table->tinyInteger('ispublic')->default(1),
        'email_id' => fn (Blueprint $table) => $table->unsignedInteger('email_id')->nullable(),
        'tpl_id' => fn (Blueprint $table) => $table->unsignedInteger('tpl_id')->nullable(),
        'dept_id' => fn (Blueprint $table) => $table->unsignedInteger('dept_id')->nullable(),
    ];

    foreach ($missingDepartmentColumns as $column => $definition) {
        if (! $schema->hasColumn('department', $column)) {
            $schema->table('department', $definition);
        }
    }

    if (! $schema->hasTable('sla')) {
        $schema->create('sla', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 64);
            $table->unsignedInteger('grace_period')->default(0);
            $table->unsignedInteger('schedule_id')->nullable();
            $table->unsignedInteger('flags')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('email')) {
        $schema->create('email', function (Blueprint $table): void {
            $table->increments('email_id');
            $table->unsignedInteger('dept_id')->nullable();
            $table->unsignedInteger('priority_id')->nullable();
            $table->unsignedInteger('topic_id')->nullable();
            $table->tinyInteger('noautoresp')->default(0);
            $table->string('email', 128);
            $table->string('name', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('email_template_group')) {
        $schema->create('email_template_group', function (Blueprint $table): void {
            $table->increments('tpl_id');
            $table->tinyInteger('isactive')->default(1);
            $table->string('name', 128);
            $table->string('lang', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('ticket')) {
        $schema->create('ticket', function (Blueprint $table): void {
            $table->increments('ticket_id');
            $table->unsignedInteger('dept_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            $table->unsignedInteger('sla_id')->nullable();
            $table->unsignedInteger('email_id')->nullable();
            $table->string('number', 32)->nullable();
            $table->string('source', 32)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->tinyInteger('isoverdue')->default(0);
            $table->tinyInteger('isanswered')->default(0);
            $table->timestamp('lastupdate')->nullable();
            $table->timestamp('lastmessage')->nullable();
            $table->timestamp('lastresponse')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    DB::connection('legacy')->table('ticket')->delete();
    EmailModel::query()->delete();
    EmailTemplateGroup::query()->delete();
    Department::query()->delete();
    Sla::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantDepartmentPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function departmentAuditPayload(
    Department $department,
    ?string $managerName = null,
    ?string $slaName = null,
    ?string $emailName = null,
    ?string $templateName = null,
    ?string $parentName = null,
): array {
    return [
        'id' => $department->id,
        'name' => $department->name,
        'sla_id' => $department->sla_id !== null ? (int) $department->sla_id : null,
        'sla_name' => $slaName,
        'manager_id' => $department->manager_id !== null ? (int) $department->manager_id : null,
        'manager_name' => $managerName,
        'email_id' => $department->email_id !== null ? (int) $department->email_id : null,
        'email_name' => $emailName,
        'template_id' => $department->tpl_id !== null ? (int) $department->tpl_id : null,
        'template_name' => $templateName,
        'dept_id' => $department->dept_id !== null ? (int) $department->dept_id : null,
        'parent_name' => $parentName,
        'signature' => $department->signature !== '' ? $department->signature : null,
        'ispublic' => (bool) ($department->ispublic ?? 0),
    ];
}

it('renders the department index for authorized admins', function (): void {
    $manager = Staff::factory()->create(['firstname' => 'Alex', 'lastname' => 'Manager']);
    $secondaryManager = Staff::factory()->create(['firstname' => 'Fin', 'lastname' => 'Owner']);
    $sla = Sla::query()->create([
        'name' => 'Business Hours',
        'grace_period' => 4,
        'created' => now(),
        'updated' => now(),
    ]);

    Department::query()->create([
        'name' => 'Customer Support',
        'manager_id' => $manager->staff_id,
        'sla_id' => $sla->id,
        'ispublic' => 1,
        'signature' => 'Thanks',
    ]);

    Department::query()->create([
        'name' => 'Finance',
        'manager_id' => $secondaryManager->staff_id,
        'sla_id' => $sla->id,
        'ispublic' => 0,
        'signature' => null,
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.update']);

    actingAs($staff, 'staff');

    get(route('admin.departments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Departments/Index')
            ->has('departments.data', 2)
            ->where('departments.data.0.name', 'Customer Support')
            ->where('departments.data.0.manager_name', $manager->displayName())
            ->where('departments.data.0.sla_name', 'Business Hours')
            ->where('departments.data.0.ispublic', true)
            ->where('departments.data.1.name', 'Finance')
            ->where('departments.data.1.manager_name', $secondaryManager->displayName())
            ->where('departments.data.1.sla_name', 'Business Hours')
            ->where('departments.data.1.ispublic', false)
        );
});

it('renders create and edit pages with department dependencies', function (): void {
    $parent = Department::query()->create(['name' => 'Operations', 'ispublic' => 1]);
    $manager = Staff::factory()->create(['firstname' => 'Jamie', 'lastname' => 'Lead']);
    $sla = Sla::query()->create([
        'name' => 'Escalation',
        'grace_period' => 2,
        'created' => now(),
        'updated' => now(),
    ]);
    $email = EmailModel::query()->create([
        'dept_id' => $parent->id,
        'email' => 'support@example.com',
        'name' => 'Support Inbox',
        'created' => now(),
        'updated' => now(),
    ]);
    $template = EmailTemplateGroup::query()->create([
        'name' => 'Default Replies',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);
    $department = Department::query()->create([
        'name' => 'Customer Support',
        'dept_id' => $parent->id,
        'manager_id' => $manager->staff_id,
        'sla_id' => $sla->id,
        'email_id' => $email->email_id,
        'tpl_id' => $template->tpl_id,
        'signature' => 'Kind regards',
        'ispublic' => 1,
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.create', 'admin.department.update']);

    actingAs($staff, 'staff');

    get(route('admin.departments.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Departments/Edit')
            ->where('department', null)
            ->has('departmentOptions')
            ->has('managerOptions')
            ->has('slaOptions')
            ->has('emailOptions')
            ->has('templateOptions')
        );

    actingAs($staff, 'staff');

    get(route('admin.departments.edit', $department))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Departments/Edit')
            ->where('department.name', 'Customer Support')
            ->where('department.dept_id', $parent->id)
            ->where('department.manager_id', $manager->staff_id)
            ->where('department.sla_id', $sla->id)
            ->where('department.email_id', $email->email_id)
            ->where('department.template_id', $template->tpl_id)
            ->where('department.signature', 'Kind regards')
            ->where('department.ispublic', true)
            ->has('departmentOptions')
        );
});

it('creates a department and writes an audit log', function (): void {
    $parent = Department::query()->create(['name' => 'Operations', 'ispublic' => 1]);
    $manager = Staff::factory()->create(['firstname' => 'Taylor', 'lastname' => 'Manager']);
    $sla = Sla::query()->create([
        'name' => 'Standard',
        'grace_period' => 8,
        'created' => now(),
        'updated' => now(),
    ]);
    $email = EmailModel::query()->create([
        'dept_id' => $parent->id,
        'email' => 'billing@example.com',
        'name' => 'Billing Queue',
        'created' => now(),
        'updated' => now(),
    ]);
    $template = EmailTemplateGroup::query()->create([
        'name' => 'Billing Replies',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.create']);

    actingAs($staff, 'staff');

    post(route('admin.departments.store'), [
        'name' => 'Billing',
        'dept_id' => $parent->id,
        'manager_id' => $manager->staff_id,
        'sla_id' => $sla->id,
        'email_id' => $email->email_id,
        'template_id' => $template->tpl_id,
        'signature' => 'Billing Team',
        'ispublic' => true,
    ])->assertRedirect();

    $department = Department::query()->where('name', 'Billing')->firstOrFail();

    assertDatabaseHas('department', [
        'id' => $department->id,
        'dept_id' => $parent->id,
        'manager_id' => $manager->staff_id,
        'sla_id' => $sla->id,
        'email_id' => $email->email_id,
        'tpl_id' => $template->tpl_id,
        'signature' => 'Billing Team',
        'ispublic' => 1,
    ], 'legacy');

    assertAuditLogged(
        'department.create',
        $department,
        null,
        departmentAuditPayload($department, $manager->displayName(), 'Standard', 'Billing Queue', 'Billing Replies', 'Operations'),
    );
});

it('rejects invalid department creation payloads', function (): void {
    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.create']);

    actingAs($staff, 'staff');

    from(route('admin.departments.create'))
        ->post(route('admin.departments.store'), [
            'name' => str_repeat('a', 129),
            'dept_id' => 9999,
            'manager_id' => 9999,
            'sla_id' => 9999,
            'email_id' => 9999,
            'template_id' => 9999,
            'signature' => ['not-valid'],
            'ispublic' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'dept_id', 'manager_id', 'sla_id', 'email_id', 'template_id', 'signature', 'ispublic']);

    expect(Department::query()->count())->toBe(0);
});

it('forbids unauthorized department creation', function (): void {
    actingAsAgent();

    post(route('admin.departments.store'), [
        'name' => 'Billing',
        'ispublic' => true,
    ])->assertForbidden();
});

it('updates a department and writes an audit log diff', function (): void {
    $oldParent = Department::query()->create(['name' => 'Operations', 'ispublic' => 1]);
    $newParent = Department::query()->create(['name' => 'Support', 'ispublic' => 1]);
    $oldManager = Staff::factory()->create(['firstname' => 'Jordan', 'lastname' => 'Owner']);
    $newManager = Staff::factory()->create(['firstname' => 'Morgan', 'lastname' => 'Lead']);
    $oldSla = Sla::query()->create(['name' => 'Legacy SLA', 'grace_period' => 12, 'created' => now(), 'updated' => now()]);
    $newSla = Sla::query()->create(['name' => 'Priority SLA', 'grace_period' => 4, 'created' => now(), 'updated' => now()]);
    $oldEmail = EmailModel::query()->create([
        'dept_id' => $oldParent->id,
        'email' => 'ops@example.com',
        'name' => 'Ops Inbox',
        'created' => now(),
        'updated' => now(),
    ]);
    $newEmail = EmailModel::query()->create([
        'dept_id' => $newParent->id,
        'email' => 'support@example.com',
        'name' => 'Support Inbox',
        'created' => now(),
        'updated' => now(),
    ]);
    $oldTemplate = EmailTemplateGroup::query()->create(['name' => 'Legacy Templates', 'lang' => 'en_US', 'created' => now(), 'updated' => now()]);
    $newTemplate = EmailTemplateGroup::query()->create(['name' => 'Priority Templates', 'lang' => 'en_US', 'created' => now(), 'updated' => now()]);
    $department = Department::query()->create([
        'name' => 'Customer Support',
        'dept_id' => $oldParent->id,
        'manager_id' => $oldManager->staff_id,
        'sla_id' => $oldSla->id,
        'email_id' => $oldEmail->email_id,
        'tpl_id' => $oldTemplate->tpl_id,
        'signature' => 'Old signature',
        'ispublic' => 0,
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.update']);

    actingAs($staff, 'staff');

    put(route('admin.departments.update', $department), [
        'name' => 'Escalations',
        'dept_id' => $newParent->id,
        'manager_id' => $newManager->staff_id,
        'sla_id' => $newSla->id,
        'email_id' => $newEmail->email_id,
        'template_id' => $newTemplate->tpl_id,
        'signature' => 'Updated signature',
        'ispublic' => true,
    ])->assertRedirect(route('admin.departments.edit', $department));

    $before = departmentAuditPayload(
        $department,
        $oldManager->displayName(),
        'Legacy SLA',
        'Ops Inbox',
        'Legacy Templates',
        'Operations',
    );

    $department->refresh();

    expect($department->name)->toBe('Escalations')
        ->and((int) $department->dept_id)->toBe($newParent->id)
        ->and((int) $department->manager_id)->toBe($newManager->staff_id)
        ->and((int) $department->sla_id)->toBe($newSla->id)
        ->and((int) $department->email_id)->toBe($newEmail->email_id)
        ->and((int) $department->tpl_id)->toBe($newTemplate->tpl_id)
        ->and($department->signature)->toBe('Updated signature')
        ->and((int) $department->ispublic)->toBe(1);

    assertAuditLogged(
        'department.update',
        $department,
        $before,
        departmentAuditPayload(
            $department,
            $newManager->displayName(),
            'Priority SLA',
            'Support Inbox',
            'Priority Templates',
            'Support',
        ),
    );
});

it('deletes a department and writes an audit log entry', function (): void {
    $manager = Staff::factory()->create(['firstname' => 'River', 'lastname' => 'Lead']);
    $sla = Sla::query()->create(['name' => 'Default SLA', 'grace_period' => 6, 'created' => now(), 'updated' => now()]);
    $email = EmailModel::query()->create([
        'email' => 'help@example.com',
        'name' => 'Help Desk',
        'created' => now(),
        'updated' => now(),
    ]);
    $template = EmailTemplateGroup::query()->create(['name' => 'Default Templates', 'lang' => 'en_US', 'created' => now(), 'updated' => now()]);
    $department = Department::query()->create([
        'name' => 'Help Desk',
        'manager_id' => $manager->staff_id,
        'sla_id' => $sla->id,
        'email_id' => $email->email_id,
        'tpl_id' => $template->tpl_id,
        'signature' => 'Desk signature',
        'ispublic' => 1,
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.departments.destroy', $department))
        ->assertRedirect(route('admin.departments.index'));

    assertDatabaseMissing('department', ['id' => $department->id], 'legacy');

    assertAuditLogged(
        'department.delete',
        $department,
        departmentAuditPayload($department, $manager->displayName(), 'Default SLA', 'Help Desk', 'Default Templates', null),
        null,
    );
});

it('blocks department deletion when foreign key references exist', function (): void {
    $department = Department::query()->create([
        'name' => 'Support',
        'ispublic' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        'dept_id' => $department->id,
        'number' => 'T-1000',
        'source' => 'Web',
        'ip_address' => '127.0.0.1',
        'isoverdue' => 0,
        'isanswered' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantDepartmentPermissions(actingAsAdmin(), ['admin.department.delete']);

    actingAs($staff, 'staff');

    test()->deleteJson(route('admin.departments.destroy', $department))
        ->assertStatus(422)
        ->assertJsonPath('errors.department.0', 'Department cannot be deleted because it is referenced by tickets.');

    assertDatabaseHas('department', ['id' => $department->id], 'legacy');

    expect(
        AdminAuditLog::query()
            ->where('action', 'department.delete')
            ->where('subject_type', 'Department')
            ->where('subject_id', $department->id)
            ->exists()
    )->toBeFalse();
});

it('forbids unauthorized department updates and deletion', function (): void {
    $department = Department::query()->create([
        'name' => 'Support',
        'ispublic' => 1,
    ]);

    actingAsAgent();

    put(route('admin.departments.update', $department), [
        'name' => 'Escalations',
        'ispublic' => true,
    ])->assertForbidden();

    delete(route('admin.departments.destroy', $department))->assertForbidden();
});
