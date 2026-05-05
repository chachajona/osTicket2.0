<?php

declare(strict_types=1);

use App\Models\Filter;
use App\Models\FilterAction;
use App\Models\FilterRule;
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

    if (! $schema->hasTable('filter')) {
        $schema->create('filter', function (Blueprint $table): void {
            $table->increments('id');
            $table->tinyInteger('isactive')->default(1);
            $table->integer('execorder')->default(0);
            $table->unsignedInteger('flags')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->tinyInteger('match_all_rules')->default(0);
            $table->tinyInteger('stop_onmatch')->default(0);
            $table->string('target', 32)->nullable();
            $table->unsignedInteger('email_id')->default(0);
            $table->string('name', 64);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('filter_rule')) {
        $schema->create('filter_rule', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('filter_id');
            $table->string('what', 64);
            $table->string('how', 64);
            $table->text('val');
            $table->tinyInteger('isactive')->default(1);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('filter_action')) {
        $schema->create('filter_action', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('filter_id');
            $table->string('type', 64);
            $table->integer('sort')->default(0);
            $table->string('target', 255);
            $table->timestamp('updated')->nullable();
        });
    }

    FilterRule::query()->delete();
    FilterAction::query()->delete();
    Filter::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantFilterPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function filterAuditPayload(Filter $filter, array $rules, array $actions, ?string $name = null, ?int $execOrder = null, ?string $notes = null, ?bool $isactive = null): array
{
    return [
        'id' => $filter->id,
        'name' => $name ?? $filter->name,
        'exec_order' => $execOrder ?? (int) ($filter->execorder ?? 0),
        'isactive' => $isactive ?? ((bool) ($filter->isactive ?? 0)),
        'notes' => $notes,
        'rules' => $rules,
        'actions' => $actions,
    ];
}

it('renders the filter index for authorized admins with nested counts', function (): void {
    $filter = Filter::query()->create([
        'name' => 'VIP Routing',
        'execorder' => 5,
        'isactive' => 1,
        'notes' => 'Priority customers',
        'created' => now(),
        'updated' => now(),
    ]);

    FilterRule::query()->create([
        'filter_id' => $filter->id,
        'what' => 'email',
        'how' => 'contains',
        'val' => '@vip.example',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    FilterAction::query()->create([
        'filter_id' => $filter->id,
        'type' => 'assign_team',
        'sort' => 10,
        'target' => 'team:2',
        'updated' => now(),
    ]);

    Filter::query()->create([
        'name' => 'Dormant',
        'execorder' => 20,
        'isactive' => 0,
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.update']);

    actingAs($staff, 'staff');

    get(route('admin.filters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Filters/Index')
            ->has('filters.data', 2)
            ->where('filters.data.0.name', 'VIP Routing')
            ->where('filters.data.0.exec_order', 5)
            ->where('filters.data.0.isactive', true)
            ->where('filters.data.0.rules_count', 1)
            ->where('filters.data.0.actions_count', 1)
            ->where('filters.data.1.name', 'Dormant')
            ->where('filters.data.1.isactive', false)
            ->where('filters.data.1.rules_count', 0)
            ->where('filters.data.1.actions_count', 0)
        );
});

it('forbids the filter index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.filters.index'))->assertForbidden();
});

it('renders create and edit pages with nested rules and actions', function (): void {
    $filter = Filter::query()->create([
        'name' => 'Routing',
        'execorder' => 3,
        'isactive' => 1,
        'notes' => 'Edit me',
        'created' => now(),
        'updated' => now(),
    ]);

    FilterRule::query()->create([
        'filter_id' => $filter->id,
        'what' => 'subject',
        'how' => 'contains',
        'val' => 'urgent',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    FilterAction::query()->create([
        'filter_id' => $filter->id,
        'type' => 'set_priority',
        'sort' => 1,
        'target' => 'high',
        'updated' => now(),
    ]);

    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.create', 'admin.filter.update']);

    actingAs($staff, 'staff');

    get(route('admin.filters.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Filters/Edit')
            ->where('filter', null)
        );

    actingAs($staff, 'staff');

    get(route('admin.filters.edit', $filter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Filters/Edit')
            ->where('filter.name', 'Routing')
            ->where('filter.exec_order', 3)
            ->where('filter.rules.0.what', 'subject')
            ->where('filter.actions.0.type', 'set_priority')
        );
});

it('creates a filter, syncs nested rules and actions, and writes an audit log', function (): void {
    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.create']);

    $rules = [
        ['what' => 'email', 'how' => 'contains', 'val' => '@vip.example', 'isactive' => true],
        ['what' => 'subject', 'how' => 'contains', 'val' => 'priority', 'isactive' => false],
    ];
    $actions = [
        ['type' => 'assign_team', 'sort' => 10, 'target' => 'team:2'],
        ['type' => 'set_priority', 'sort' => 20, 'target' => 'high'],
    ];

    actingAs($staff, 'staff');

    post(route('admin.filters.store'), [
        'name' => 'VIP Routing',
        'exec_order' => 5,
        'isactive' => true,
        'notes' => 'Priority customers',
        'rules' => $rules,
        'actions' => $actions,
    ])->assertRedirect();

    $filter = Filter::query()->where('name', 'VIP Routing')->firstOrFail();

    assertDatabaseHas('filter', [
        'id' => $filter->id,
        'name' => 'VIP Routing',
        'execorder' => 5,
        'isactive' => 1,
        'notes' => 'Priority customers',
    ], 'legacy');

    assertDatabaseHas('filter_rule', [
        'filter_id' => $filter->id,
        'what' => 'email',
        'how' => 'contains',
        'val' => '@vip.example',
        'isactive' => 1,
    ], 'legacy');

    assertDatabaseHas('filter_action', [
        'filter_id' => $filter->id,
        'type' => 'assign_team',
        'sort' => 10,
        'target' => 'team:2',
    ], 'legacy');

    assertAuditLogged(
        'filter.create',
        $filter,
        null,
        filterAuditPayload(
            $filter,
            [
                ['what' => 'email', 'how' => 'contains', 'val' => '@vip.example', 'isactive' => true],
                ['what' => 'subject', 'how' => 'contains', 'val' => 'priority', 'isactive' => false],
            ],
            [
                ['type' => 'assign_team', 'sort' => 10, 'target' => 'team:2'],
                ['type' => 'set_priority', 'sort' => 20, 'target' => 'high'],
            ],
            'VIP Routing',
            5,
            'Priority customers',
            true,
        ),
    );
});

it('rejects invalid filter creation payloads', function (): void {
    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.create']);

    actingAs($staff, 'staff');

    from(route('admin.filters.create'))
        ->post(route('admin.filters.store'), [
            'name' => '',
            'exec_order' => 'invalid',
            'isactive' => 'invalid',
            'notes' => str_repeat('a', 256),
            'rules' => [
                ['what' => '', 'how' => '', 'val' => '', 'isactive' => 'invalid'],
            ],
            'actions' => [
                ['type' => '', 'sort' => 'invalid', 'target' => ''],
            ],
        ])
        ->assertSessionHasErrors([
            'name',
            'exec_order',
            'isactive',
            'notes',
            'rules.0.what',
            'rules.0.how',
            'rules.0.val',
            'rules.0.isactive',
            'actions.0.type',
            'actions.0.sort',
            'actions.0.target',
        ]);

    expect(Filter::query()->count())->toBe(0);
});

it('forbids unauthorized filter creation', function (): void {
    actingAsAgent();

    post(route('admin.filters.store'), [
        'name' => 'VIP Routing',
        'exec_order' => 1,
        'isactive' => true,
    ])->assertForbidden();
});

it('updates a filter, replaces nested rules and actions, and writes an audit log diff', function (): void {
    $filter = Filter::query()->create([
        'name' => 'Routing',
        'execorder' => 3,
        'isactive' => 1,
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);

    FilterRule::query()->create([
        'filter_id' => $filter->id,
        'what' => 'subject',
        'how' => 'contains',
        'val' => 'urgent',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);
    FilterRule::query()->create([
        'filter_id' => $filter->id,
        'what' => 'email',
        'how' => 'contains',
        'val' => '@old.example',
        'isactive' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    FilterAction::query()->create([
        'filter_id' => $filter->id,
        'type' => 'assign_team',
        'sort' => 10,
        'target' => 'team:1',
        'updated' => now(),
    ]);

    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.update']);

    actingAs($staff, 'staff');

    put(route('admin.filters.update', $filter), [
        'name' => 'Escalation Routing',
        'exec_order' => 8,
        'isactive' => false,
        'notes' => 'Updated notes',
        'rules' => [
            ['what' => 'department', 'how' => 'is', 'val' => 'billing', 'isactive' => true],
        ],
        'actions' => [
            ['type' => 'set_priority', 'sort' => 5, 'target' => 'critical'],
            ['type' => 'assign_team', 'sort' => 15, 'target' => 'team:3'],
        ],
    ])->assertRedirect(route('admin.filters.edit', $filter));

    $before = filterAuditPayload(
        $filter,
        [
            ['what' => 'subject', 'how' => 'contains', 'val' => 'urgent', 'isactive' => true],
            ['what' => 'email', 'how' => 'contains', 'val' => '@old.example', 'isactive' => false],
        ],
        [
            ['type' => 'assign_team', 'sort' => 10, 'target' => 'team:1'],
        ],
        'Routing',
        3,
        'Old notes',
        true,
    );

    $filter->refresh();

    expect($filter->name)->toBe('Escalation Routing')
        ->and((int) $filter->execorder)->toBe(8)
        ->and((int) $filter->isactive)->toBe(0)
        ->and($filter->notes)->toBe('Updated notes');

    expect(FilterRule::query()->where('filter_id', $filter->id)->count())->toBe(1)
        ->and(FilterAction::query()->where('filter_id', $filter->id)->count())->toBe(2);

    assertDatabaseMissing('filter_rule', [
        'filter_id' => $filter->id,
        'what' => 'subject',
        'how' => 'contains',
        'val' => 'urgent',
    ], 'legacy');

    assertAuditLogged(
        'filter.update',
        $filter,
        $before,
        filterAuditPayload(
            $filter,
            [
                ['what' => 'department', 'how' => 'is', 'val' => 'billing', 'isactive' => true],
            ],
            [
                ['type' => 'set_priority', 'sort' => 5, 'target' => 'critical'],
                ['type' => 'assign_team', 'sort' => 15, 'target' => 'team:3'],
            ],
            'Escalation Routing',
            8,
            'Updated notes',
            false,
        ),
    );
});

it('deletes a filter, cascades nested rows, and writes an audit log entry', function (): void {
    $filter = Filter::query()->create([
        'name' => 'Delete Me',
        'execorder' => 4,
        'isactive' => 1,
        'notes' => 'To be removed',
        'created' => now(),
        'updated' => now(),
    ]);

    FilterRule::query()->create([
        'filter_id' => $filter->id,
        'what' => 'subject',
        'how' => 'contains',
        'val' => 'obsolete',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    FilterAction::query()->create([
        'filter_id' => $filter->id,
        'type' => 'reject',
        'sort' => 1,
        'target' => 'spam',
        'updated' => now(),
    ]);

    $staff = grantFilterPermissions(actingAsAdmin(), ['admin.filter.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.filters.destroy', $filter))
        ->assertRedirect(route('admin.filters.index'));

    assertDatabaseMissing('filter', ['id' => $filter->id], 'legacy');
    assertDatabaseMissing('filter_rule', ['filter_id' => $filter->id], 'legacy');
    assertDatabaseMissing('filter_action', ['filter_id' => $filter->id], 'legacy');

    assertAuditLogged(
        'filter.delete',
        $filter,
        filterAuditPayload(
            $filter,
            [
                ['what' => 'subject', 'how' => 'contains', 'val' => 'obsolete', 'isactive' => true],
            ],
            [
                ['type' => 'reject', 'sort' => 1, 'target' => 'spam'],
            ],
            'Delete Me',
            4,
            'To be removed',
            true,
        ),
        null,
    );
});

it('forbids unauthorized filter updates and deletion', function (): void {
    $filter = Filter::query()->create([
        'name' => 'Locked',
        'execorder' => 1,
        'isactive' => 1,
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAsAgent();

    put(route('admin.filters.update', $filter), [
        'name' => 'Unlocked',
        'exec_order' => 2,
        'isactive' => true,
        'rules' => [],
        'actions' => [],
    ])->assertForbidden();

    delete(route('admin.filters.destroy', $filter))->assertForbidden();
});
