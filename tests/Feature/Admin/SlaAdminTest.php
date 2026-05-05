<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\Sla;
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

    if (! $schema->hasTable('schedule')) {
        $schema->create('schedule', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('flags')->default(0);
            $table->string('name', 128);
            $table->string('timezone', 64)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
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

    Sla::query()->delete();
    Schedule::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantSlaPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function slaAuditPayload(Sla $sla, ?string $scheduleName = null): array
{
    return [
        'id' => $sla->id,
        'name' => $sla->name,
        'grace_period' => (int) $sla->grace_period,
        'schedule_id' => $sla->schedule_id !== null ? (int) $sla->schedule_id : null,
        'schedule' => $scheduleName,
        'notes' => $sla->notes !== '' ? $sla->notes : null,
        'flags' => (int) ($sla->flags ?? 0),
    ];
}

it('renders the sla index for authorized admins', function (): void {
    $businessHours = Schedule::query()->create([
        'name' => 'Business Hours',
        'timezone' => 'UTC',
        'description' => 'Main support schedule',
    ]);
    $afterHours = Schedule::query()->create([
        'name' => 'After Hours',
        'timezone' => 'UTC',
        'description' => 'Escalation schedule',
    ]);

    Sla::query()->create([
        'name' => 'Priority 1',
        'grace_period' => 4,
        'schedule_id' => $afterHours->getKey(),
        'flags' => 1,
        'notes' => 'Critical incidents',
        'created' => now(),
        'updated' => now(),
    ]);

    Sla::query()->create([
        'name' => 'Standard',
        'grace_period' => 24,
        'schedule_id' => $businessHours->getKey(),
        'flags' => 0,
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.update']);

    actingAs($staff, 'staff');

    get(route('admin.slas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Slas/Index')
            ->has('slas.data', 2)
            ->where('slas.data.0.name', 'Priority 1')
            ->where('slas.data.0.grace_period', 4)
            ->where('slas.data.0.schedule', 'After Hours')
            ->where('slas.data.1.name', 'Standard')
            ->where('slas.data.1.schedule', 'Business Hours')
        );
});

it('forbids the sla index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.slas.index'))->assertForbidden();
});

it('renders create and edit pages for authorized admins', function (): void {
    $schedule = Schedule::query()->create([
        'name' => 'Business Hours',
        'timezone' => 'UTC',
    ]);
    $sla = Sla::query()->create([
        'name' => 'Standard Response',
        'grace_period' => 12,
        'schedule_id' => $schedule->getKey(),
        'flags' => 1,
        'notes' => 'Default notes',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.create', 'admin.sla.update']);

    actingAs($staff, 'staff');

    get(route('admin.slas.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Slas/Edit')
            ->where('sla', null)
        );

    actingAs($staff, 'staff');

    get(route('admin.slas.edit', $sla))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Slas/Edit')
            ->where('sla.name', 'Standard Response')
            ->where('sla.grace_period', 12)
            ->where('sla.schedule_id', $schedule->id)
            ->where('sla.schedule', 'Business Hours')
        );
});

it('creates an sla and writes an audit log', function (): void {
    $schedule = Schedule::query()->create([
        'name' => 'Business Hours',
        'timezone' => 'UTC',
    ]);
    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.create']);

    actingAs($staff, 'staff');

    post(route('admin.slas.store'), [
        'name' => 'Standard Response',
        'grace_period' => 12,
        'schedule_id' => $schedule->getKey(),
        'notes' => 'Default response target',
        'flags' => 2,
    ])->assertRedirect();

    $sla = Sla::query()->where('name', 'Standard Response')->firstOrFail();
    $sla->load('schedule');

    assertDatabaseHas('sla', [
        'id' => $sla->getKey(),
        'name' => 'Standard Response',
        'grace_period' => 12,
        'schedule_id' => $schedule->getKey(),
        'flags' => 2,
        'notes' => 'Default response target',
    ], 'legacy');

    assertAuditLogged('sla.create', $sla, null, slaAuditPayload($sla, 'Business Hours'));
});

it('rejects invalid sla creation payloads', function (): void {
    Sla::query()->create([
        'name' => 'Existing',
        'grace_period' => 8,
        'schedule_id' => null,
        'flags' => 0,
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.create']);

    actingAs($staff, 'staff');

    from(route('admin.slas.create'))
        ->post(route('admin.slas.store'), [
            'name' => 'Existing',
            'grace_period' => -1,
            'schedule_id' => 9999,
            'notes' => str_repeat('a', 256),
            'flags' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'grace_period', 'schedule_id', 'notes', 'flags']);

    expect(Sla::query()->count())->toBe(1);
});

it('forbids unauthorized sla creation', function (): void {
    actingAsAgent();

    post(route('admin.slas.store'), [
        'name' => 'Standard Response',
        'grace_period' => 12,
        'flags' => 0,
    ])->assertForbidden();
});

it('updates an sla and writes an audit log diff', function (): void {
    $oldSchedule = Schedule::query()->create([
        'name' => 'Business Hours',
        'timezone' => 'UTC',
    ]);
    $newSchedule = Schedule::query()->create([
        'name' => 'Weekend Coverage',
        'timezone' => 'UTC',
    ]);
    $sla = Sla::query()->create([
        'name' => 'Standard Response',
        'grace_period' => 12,
        'schedule_id' => $oldSchedule->getKey(),
        'flags' => 1,
        'notes' => 'Original notes',
        'created' => now(),
        'updated' => now(),
    ]);
    $sla->load('schedule');

    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.update']);

    actingAs($staff, 'staff');

    put(route('admin.slas.update', $sla), [
        'name' => 'Weekend Response',
        'grace_period' => 6,
        'schedule_id' => $newSchedule->getKey(),
        'notes' => 'Updated notes',
        'flags' => 3,
    ])->assertRedirect(route('admin.slas.edit', $sla));

    $before = slaAuditPayload($sla, 'Business Hours');

    $sla->refresh()->load('schedule');

    expect($sla->name)->toBe('Weekend Response')
        ->and((int) $sla->grace_period)->toBe(6)
        ->and((int) $sla->schedule_id)->toBe($newSchedule->id)
        ->and($sla->notes)->toBe('Updated notes')
        ->and((int) $sla->flags)->toBe(3);

    assertAuditLogged('sla.update', $sla, $before, slaAuditPayload($sla, 'Weekend Coverage'));
});

it('deletes an sla and writes an audit log entry', function (): void {
    $schedule = Schedule::query()->create([
        'name' => 'Business Hours',
        'timezone' => 'UTC',
    ]);
    $sla = Sla::query()->create([
        'name' => 'Standard Response',
        'grace_period' => 12,
        'schedule_id' => $schedule->getKey(),
        'flags' => 1,
        'notes' => 'Default coverage',
        'created' => now(),
        'updated' => now(),
    ]);
    $sla->load('schedule');

    $staff = grantSlaPermissions(actingAsAdmin(), ['admin.sla.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.slas.destroy', $sla))
        ->assertRedirect(route('admin.slas.index'));

    assertDatabaseMissing('sla', ['id' => $sla->getKey()], 'legacy');

    assertAuditLogged('sla.delete', $sla, slaAuditPayload($sla, 'Business Hours'), null);
});
