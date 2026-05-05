<?php

declare(strict_types=1);

use App\Models\Admin\AdminAuditLog;
use App\Models\Department;
use App\Models\DynamicForm;
use App\Models\HelpTopic;
use App\Models\HelpTopicForm;
use App\Models\Sla;
use App\Models\Staff;
use App\Models\Team;
use App\Models\TicketPriority;
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
            $table->unsignedInteger('tpl_id')->default(0);
            $table->unsignedInteger('sla_id')->default(0);
            $table->unsignedInteger('manager_id')->default(0);
            $table->string('name', 128);
            $table->text('signature')->nullable();
            $table->tinyInteger('ispublic')->default(1);
        });
    }

    if (! $schema->hasTable('sla')) {
        $schema->create('sla', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 64)->unique();
            $table->unsignedInteger('grace_period')->default(0);
            $table->unsignedInteger('schedule_id')->nullable();
            $table->unsignedInteger('flags')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
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

    if (! $schema->hasTable('ticket_priority')) {
        $schema->create('ticket_priority', function (Blueprint $table): void {
            $table->increments('priority_id');
            $table->string('priority', 32);
            $table->string('priority_desc', 255)->nullable();
            $table->string('priority_color', 16)->nullable();
            $table->unsignedInteger('priority_urgency')->default(0);
            $table->tinyInteger('ispublic')->default(1);
        });
    }

    if (! $schema->hasTable('form')) {
        $schema->create('form', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pid')->default(0);
            $table->string('type', 64)->default('G');
            $table->unsignedInteger('flags')->default(0);
            $table->string('title', 128);
            $table->text('instructions')->nullable();
            $table->string('name', 64)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('form_field')) {
        $schema->create('form_field', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('form_id');
            $table->unsignedInteger('flags')->default(0);
            $table->string('type', 64);
            $table->string('label', 128);
            $table->string('name', 128);
            $table->text('configuration')->nullable();
            $table->unsignedInteger('sort')->default(1);
            $table->string('hint', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('help_topic')) {
        $schema->create('help_topic', function (Blueprint $table): void {
            $table->increments('topic_id');
            $table->unsignedInteger('topic_pid')->default(0);
            $table->tinyInteger('ispublic')->default(1);
            $table->tinyInteger('noautoresp')->default(0);
            $table->unsignedInteger('flags')->default(0);
            $table->unsignedInteger('status_id')->default(0);
            $table->unsignedInteger('priority_id')->nullable();
            $table->unsignedInteger('dept_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            $table->unsignedInteger('team_id')->nullable();
            $table->unsignedInteger('sla_id')->nullable();
            $table->unsignedInteger('form_id')->nullable();
            $table->unsignedInteger('page_id')->default(0);
            $table->unsignedInteger('sequence_id')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->tinyInteger('isactive')->default(1);
            $table->tinyInteger('disabled')->default(0);
            $table->string('topic', 128);
            $table->string('number_format', 32)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('help_topic_form')) {
        $schema->create('help_topic_form', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('topic_id');
            $table->unsignedInteger('form_id');
            $table->unsignedInteger('sort')->default(0);
            $table->text('extra')->nullable();
        });
    }

    HelpTopicForm::query()->delete();
    HelpTopic::query()->delete();
    DynamicForm::query()->delete();
    TicketPriority::query()->delete();
    Team::query()->delete();
    Sla::query()->delete();
    Department::query()->delete();
    AdminAuditLog::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantHelpTopicPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

/**
 * @return array<string, mixed>
 */
function helpTopicAuditPayload(HelpTopic $helpTopic, array $overrides = []): array
{
    return array_merge([
        'id' => $helpTopic->topic_id,
        'topic' => $helpTopic->topic,
        'topic_pid' => (int) ($helpTopic->topic_pid ?? 0) !== 0 ? (int) $helpTopic->topic_pid : null,
        'parent_topic' => null,
        'dept_id' => $helpTopic->dept_id !== null ? (int) $helpTopic->dept_id : null,
        'department_name' => null,
        'sla_id' => $helpTopic->sla_id !== null ? (int) $helpTopic->sla_id : null,
        'sla_name' => null,
        'staff_id' => $helpTopic->staff_id !== null ? (int) $helpTopic->staff_id : null,
        'staff_name' => null,
        'team_id' => $helpTopic->team_id !== null ? (int) $helpTopic->team_id : null,
        'team_name' => null,
        'priority_id' => $helpTopic->priority_id !== null ? (int) $helpTopic->priority_id : null,
        'ispublic' => (bool) ($helpTopic->ispublic ?? 0),
        'isactive' => (bool) ($helpTopic->isactive ?? 0),
        'noautoresp' => (bool) ($helpTopic->noautoresp ?? 0),
        'notes' => $helpTopic->notes !== '' ? $helpTopic->notes : null,
    ], $overrides);
}

it('renders the help topic index for authorized admins', function (): void {
    $department = Department::query()->create(['name' => 'Billing']);
    $sla = Sla::query()->create([
        'name' => 'Priority 1',
        'grace_period' => 4,
        'created' => now(),
        'updated' => now(),
    ]);

    HelpTopic::query()->create([
        'topic' => 'Billing Question',
        'dept_id' => $department->id,
        'sla_id' => $sla->id,
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    HelpTopic::query()->create([
        'topic' => 'Internal Escalation',
        'ispublic' => 0,
        'isactive' => 0,
        'disabled' => 1,
        'noautoresp' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.update']);

    actingAs($staff, 'staff');

    get(route('admin.help-topics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/HelpTopics/Index')
            ->has('helpTopics.data', 2)
            ->where('helpTopics.data.0.topic', 'Billing Question')
            ->where('helpTopics.data.0.department_name', 'Billing')
            ->where('helpTopics.data.0.sla_name', 'Priority 1')
            ->where('helpTopics.data.0.isactive', true)
            ->where('helpTopics.data.1.topic', 'Internal Escalation')
            ->where('helpTopics.data.1.isactive', false)
        );
});

it('forbids the help topic index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.help-topics.index'))->assertForbidden();
});

it('renders create and edit pages with options and read only form mappings', function (): void {
    $parent = HelpTopic::query()->create([
        'topic' => 'Support',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $department = Department::query()->create(['name' => 'Billing']);
    $sla = Sla::query()->create(['name' => 'Standard', 'grace_period' => 24, 'created' => now(), 'updated' => now()]);
    $assignedStaff = Staff::factory()->create();
    $team = Team::query()->create(['name' => 'Escalations', 'created' => now(), 'updated' => now()]);
    $priority = TicketPriority::query()->create(['priority' => 'High', 'priority_urgency' => 2]);
    $form = DynamicForm::query()->create(['title' => 'Billing Intake', 'name' => 'billing-intake', 'created' => now(), 'updated' => now()]);
    $attachedForm = DynamicForm::query()->create(['title' => 'Follow Up', 'name' => 'follow-up', 'created' => now(), 'updated' => now()]);

    Schema::connection('legacy')->table('form_field', function () {
        // no-op to ensure table exists for sqlite before inserts in some environments
    });

    DB::connection('legacy')->table('form_field')->insert([
        ['form_id' => $form->id, 'type' => 'text', 'label' => 'Account Number', 'name' => 'account_number', 'sort' => 1],
        ['form_id' => $attachedForm->id, 'type' => 'textarea', 'label' => 'Context', 'name' => 'context', 'sort' => 1],
    ]);

    $helpTopic = HelpTopic::query()->create([
        'topic' => 'Billing Question',
        'topic_pid' => $parent->topic_id,
        'dept_id' => $department->id,
        'sla_id' => $sla->id,
        'staff_id' => $assignedStaff->staff_id,
        'team_id' => $team->team_id,
        'priority_id' => $priority->priority_id,
        'form_id' => $form->id,
        'notes' => 'Existing notes',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    HelpTopicForm::query()->create([
        'topic_id' => $helpTopic->topic_id,
        'form_id' => $attachedForm->id,
        'sort' => 1,
        'extra' => '{}',
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.create', 'admin.helptopic.update']);

    actingAs($staff, 'staff');

    get(route('admin.help-topics.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/HelpTopics/Edit')
            ->where('helpTopic', null)
            ->has('parentTopicOptions', 2)
            ->has('departmentOptions', 1)
            ->has('slaOptions', 1)
            ->has('staffOptions', 2)
            ->has('teamOptions', 1)
            ->has('priorityOptions', 1)
            ->where('formMappings', [])
        );

    actingAs($staff, 'staff');

    get(route('admin.help-topics.edit', $helpTopic))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/HelpTopics/Edit')
            ->where('helpTopic.topic', 'Billing Question')
            ->where('helpTopic.topic_pid', $parent->topic_id)
            ->where('helpTopic.dept_id', $department->id)
            ->where('helpTopic.sla_id', $sla->id)
            ->where('helpTopic.staff_id', $assignedStaff->staff_id)
            ->where('helpTopic.team_id', $team->team_id)
            ->where('helpTopic.priority_id', $priority->priority_id)
            ->has('formMappings', 2)
            ->where('formMappings.0.title', 'Billing Intake')
            ->where('formMappings.0.source', 'Default form')
            ->where('formMappings.0.fields.0.label', 'Account Number')
        );
});

it('creates a help topic and writes an audit log', function (): void {
    $parent = HelpTopic::query()->create([
        'topic' => 'Support',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $department = Department::query()->create(['name' => 'Billing']);
    $sla = Sla::query()->create(['name' => 'Standard', 'grace_period' => 24, 'created' => now(), 'updated' => now()]);
    $assignedStaff = Staff::factory()->create();
    $team = Team::query()->create(['name' => 'Escalations', 'created' => now(), 'updated' => now()]);
    $priority = TicketPriority::query()->create(['priority' => 'High', 'priority_urgency' => 2]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.create']);

    actingAs($staff, 'staff');

    post(route('admin.help-topics.store'), [
        'topic' => 'Billing Question',
        'topic_pid' => $parent->topic_id,
        'dept_id' => $department->id,
        'sla_id' => $sla->id,
        'staff_id' => $assignedStaff->staff_id,
        'team_id' => $team->team_id,
        'priority_id' => $priority->priority_id,
        'ispublic' => true,
        'isactive' => true,
        'noautoresp' => false,
        'notes' => 'Route to billing',
    ])->assertRedirect();

    $helpTopic = HelpTopic::query()->where('topic', 'Billing Question')->firstOrFail();
    $helpTopic->load(['parent', 'department', 'sla', 'staff', 'team']);

    assertDatabaseHas('help_topic', [
        'topic_id' => $helpTopic->topic_id,
        'topic_pid' => $parent->topic_id,
        'dept_id' => $department->id,
        'sla_id' => $sla->id,
        'staff_id' => $assignedStaff->staff_id,
        'team_id' => $team->team_id,
        'priority_id' => $priority->priority_id,
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'disabled' => 0,
        'notes' => 'Route to billing',
    ], 'legacy');

    assertAuditLogged('help_topic.create', $helpTopic, null, helpTopicAuditPayload($helpTopic, [
        'parent_topic' => 'Support',
        'department_name' => 'Billing',
        'sla_name' => 'Standard',
        'staff_name' => $assignedStaff->displayName(),
        'team_name' => 'Escalations',
    ]));
});

it('excludes child help topics from parent options when editing', function (): void {
    $root = HelpTopic::query()->create([
        'topic' => 'Billing',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $child = HelpTopic::query()->create([
        'topic' => 'Billing Child',
        'topic_pid' => $root->topic_id,
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $grandchild = HelpTopic::query()->create([
        'topic' => 'Billing Grandchild',
        'topic_pid' => $child->topic_id,
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $unrelated = HelpTopic::query()->create([
        'topic' => 'Support',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.update']);

    actingAs($staff, 'staff');

    get(route('admin.help-topics.edit', $root))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/HelpTopics/Edit')
            ->where('parentTopicOptions', fn (mixed $options): bool => collect($options)->pluck('id')->all() === [$unrelated->topic_id])
        );

    expect($root->descendantIds())->toBe([$child->topic_id, $grandchild->topic_id]);
});

it('rejects invalid help topic creation payloads', function (): void {
    $helpTopic = HelpTopic::query()->create([
        'topic' => 'Support',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.create']);

    actingAs($staff, 'staff');

    from(route('admin.help-topics.create'))
        ->post(route('admin.help-topics.store'), [
            'topic' => '',
            'topic_pid' => 999999,
            'dept_id' => 999999,
            'sla_id' => 999999,
            'staff_id' => 999999,
            'team_id' => 999999,
            'priority_id' => 'high',
            'ispublic' => 'invalid',
            'isactive' => 'invalid',
            'noautoresp' => 'invalid',
            'notes' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors(['topic', 'topic_pid', 'dept_id', 'sla_id', 'staff_id', 'team_id', 'priority_id', 'ispublic', 'isactive', 'noautoresp', 'notes']);

    expect(HelpTopic::query()->count())->toBe(1)
        ->and($helpTopic->exists)->toBeTrue();
});

it('forbids unauthorized help topic creation', function (): void {
    actingAsAgent();

    post(route('admin.help-topics.store'), [
        'topic' => 'Billing Question',
        'ispublic' => true,
        'isactive' => true,
        'noautoresp' => false,
    ])->assertForbidden();
});

it('updates a help topic and writes an audit log diff', function (): void {
    $oldParent = HelpTopic::query()->create([
        'topic' => 'Support',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $newParent = HelpTopic::query()->create([
        'topic' => 'Escalations',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $oldDepartment = Department::query()->create(['name' => 'Billing']);
    $newDepartment = Department::query()->create(['name' => 'Support']);
    $oldSla = Sla::query()->create(['name' => 'Standard', 'grace_period' => 24, 'created' => now(), 'updated' => now()]);
    $newSla = Sla::query()->create(['name' => 'Urgent', 'grace_period' => 4, 'created' => now(), 'updated' => now()]);
    $oldStaff = Staff::factory()->create();
    $newStaff = Staff::factory()->create();
    $oldTeam = Team::query()->create(['name' => 'Escalations', 'created' => now(), 'updated' => now()]);
    $newTeam = Team::query()->create(['name' => 'VIP', 'created' => now(), 'updated' => now()]);
    $oldPriority = TicketPriority::query()->create(['priority' => 'Normal', 'priority_urgency' => 1]);
    $newPriority = TicketPriority::query()->create(['priority' => 'Critical', 'priority_urgency' => 3]);

    $helpTopic = HelpTopic::query()->create([
        'topic' => 'Billing Question',
        'topic_pid' => $oldParent->topic_id,
        'dept_id' => $oldDepartment->id,
        'sla_id' => $oldSla->id,
        'staff_id' => $oldStaff->staff_id,
        'team_id' => $oldTeam->team_id,
        'priority_id' => $oldPriority->priority_id,
        'ispublic' => 1,
        'isactive' => 1,
        'disabled' => 0,
        'noautoresp' => 0,
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);
    $helpTopic->load(['parent', 'department', 'sla', 'staff', 'team']);

    $before = helpTopicAuditPayload($helpTopic, [
        'parent_topic' => 'Support',
        'department_name' => 'Billing',
        'sla_name' => 'Standard',
        'staff_name' => $oldStaff->displayName(),
        'team_name' => 'Escalations',
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.update']);

    actingAs($staff, 'staff');

    put(route('admin.help-topics.update', $helpTopic), [
        'topic' => 'VIP Billing',
        'topic_pid' => $newParent->topic_id,
        'dept_id' => $newDepartment->id,
        'sla_id' => $newSla->id,
        'staff_id' => $newStaff->staff_id,
        'team_id' => $newTeam->team_id,
        'priority_id' => $newPriority->priority_id,
        'ispublic' => false,
        'isactive' => false,
        'noautoresp' => true,
        'notes' => 'Updated notes',
    ])->assertRedirect(route('admin.help-topics.edit', $helpTopic));

    $helpTopic->refresh()->load(['parent', 'department', 'sla', 'staff', 'team']);

    expect($helpTopic->topic)->toBe('VIP Billing')
        ->and((int) $helpTopic->topic_pid)->toBe($newParent->topic_id)
        ->and((int) $helpTopic->dept_id)->toBe($newDepartment->id)
        ->and((int) $helpTopic->sla_id)->toBe($newSla->id)
        ->and((int) $helpTopic->staff_id)->toBe($newStaff->staff_id)
        ->and((int) $helpTopic->team_id)->toBe($newTeam->team_id)
        ->and((int) $helpTopic->priority_id)->toBe($newPriority->priority_id)
        ->and((int) $helpTopic->ispublic)->toBe(0)
        ->and((int) $helpTopic->isactive)->toBe(0)
        ->and((int) $helpTopic->disabled)->toBe(1)
        ->and((int) $helpTopic->noautoresp)->toBe(1)
        ->and($helpTopic->notes)->toBe('Updated notes');

    assertAuditLogged('help_topic.update', $helpTopic, $before, helpTopicAuditPayload($helpTopic, [
        'parent_topic' => 'Escalations',
        'department_name' => 'Support',
        'sla_name' => 'Urgent',
        'staff_name' => $newStaff->displayName(),
        'team_name' => 'VIP',
    ]));
});

it('rejects making a child help topic the parent during update', function (): void {
    $root = HelpTopic::query()->create([
        'topic' => 'Billing',
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);
    $child = HelpTopic::query()->create([
        'topic' => 'Billing Child',
        'topic_pid' => $root->topic_id,
        'ispublic' => 1,
        'isactive' => 1,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.update']);

    actingAs($staff, 'staff');

    from(route('admin.help-topics.edit', $root))
        ->put(route('admin.help-topics.update', $root), [
            'topic' => 'Billing',
            'topic_pid' => $child->topic_id,
            'ispublic' => true,
            'isactive' => true,
            'noautoresp' => false,
        ])
        ->assertSessionHasErrors(['topic_pid']);

    expect((int) $root->refresh()->topic_pid)->toBe(0);
});

it('deletes a help topic and writes an audit log entry', function (): void {
    $department = Department::query()->create(['name' => 'Billing']);
    $sla = Sla::query()->create(['name' => 'Standard', 'grace_period' => 24, 'created' => now(), 'updated' => now()]);
    $assignedStaff = Staff::factory()->create();
    $team = Team::query()->create(['name' => 'Escalations', 'created' => now(), 'updated' => now()]);

    $helpTopic = HelpTopic::query()->create([
        'topic' => 'Billing Question',
        'dept_id' => $department->id,
        'sla_id' => $sla->id,
        'staff_id' => $assignedStaff->staff_id,
        'team_id' => $team->team_id,
        'ispublic' => 1,
        'isactive' => 1,
        'disabled' => 0,
        'noautoresp' => 0,
        'notes' => 'Delete me',
        'created' => now(),
        'updated' => now(),
    ]);
    $helpTopic->load(['department', 'sla', 'staff', 'team']);

    $staff = grantHelpTopicPermissions(actingAsAdmin(), ['admin.helptopic.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.help-topics.destroy', $helpTopic))
        ->assertRedirect(route('admin.help-topics.index'));

    assertDatabaseMissing('help_topic', ['topic_id' => $helpTopic->topic_id], 'legacy');

    assertAuditLogged('help_topic.delete', $helpTopic, helpTopicAuditPayload($helpTopic, [
        'department_name' => 'Billing',
        'sla_name' => 'Standard',
        'staff_name' => $assignedStaff->displayName(),
        'team_name' => 'Escalations',
    ]), null);
});

it('forbids unauthorized help topic updates and deletion', function (): void {
    $helpTopic = HelpTopic::query()->create([
        'topic' => 'Billing Question',
        'ispublic' => 1,
        'isactive' => 1,
        'disabled' => 0,
        'noautoresp' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    actingAsAgent();

    put(route('admin.help-topics.update', $helpTopic), [
        'topic' => 'Updated Topic',
        'ispublic' => true,
        'isactive' => true,
        'noautoresp' => false,
    ])->assertForbidden();

    delete(route('admin.help-topics.destroy', $helpTopic))->assertForbidden();
});
