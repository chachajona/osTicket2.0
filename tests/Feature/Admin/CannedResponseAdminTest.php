<?php

declare(strict_types=1);

use App\Models\CannedResponse;
use App\Models\Department;
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

    $schema->dropIfExists('department');
    $schema->create('department', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 128);
    });

    $schema->dropIfExists('canned_response');
    $schema->create('canned_response', function (Blueprint $table): void {
        $table->increments('canned_id');
        $table->unsignedInteger('dept_id')->nullable();
        $table->string('title', 255);
        $table->text('response');
        $table->string('notes', 255)->nullable();
        $table->tinyInteger('isactive')->default(1);
        $table->string('lang', 16)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
    });

    CannedResponse::query()->delete();
    Department::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantCannedPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function cannedResponseAuditPayload(CannedResponse $cannedResponse, ?string $departmentName = null): array
{
    return [
        'id' => $cannedResponse->canned_id,
        'title' => $cannedResponse->title,
        'response' => $cannedResponse->response,
        'notes' => $cannedResponse->notes !== '' ? $cannedResponse->notes : null,
        'dept_id' => $cannedResponse->dept_id !== null ? (int) $cannedResponse->dept_id : null,
        'department_name' => $departmentName,
        'isactive' => (bool) ($cannedResponse->isactive ?? 0),
    ];
}

it('renders the canned response index for authorized admins', function (): void {
    $support = Department::query()->create(['name' => 'Support']);
    $sales = Department::query()->create(['name' => 'Sales']);

    CannedResponse::query()->create([
        'dept_id' => $support->getKey(),
        'title' => 'Password Reset',
        'response' => 'Reset your password using the link provided.',
        'notes' => 'Used by support',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    CannedResponse::query()->create([
        'dept_id' => $sales->getKey(),
        'title' => 'Plan Upgrade',
        'response' => 'Here are upgrade options.',
        'notes' => '',
        'isactive' => 0,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.update']);

    actingAs($staff, 'staff');

    get(route('admin.canned-responses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CannedResponses/Index')
            ->has('cannedResponses.data', 2)
            ->where('cannedResponses.data.0.title', 'Password Reset')
            ->where('cannedResponses.data.0.department_name', 'Support')
            ->where('cannedResponses.data.0.isactive', true)
            ->where('cannedResponses.data.1.title', 'Plan Upgrade')
            ->where('cannedResponses.data.1.department_name', 'Sales')
            ->where('cannedResponses.data.1.isactive', false)
        );
});

it('forbids the canned response index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.canned-responses.index'))->assertForbidden();
});

it('renders create and edit pages with department options for authorized admins', function (): void {
    $support = Department::query()->create(['name' => 'Support']);
    Department::query()->create(['name' => 'Sales']);
    $cannedResponse = CannedResponse::query()->create([
        'dept_id' => $support->getKey(),
        'title' => 'Password Reset',
        'response' => 'Reset response',
        'notes' => 'Used by support',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.create', 'admin.canned.update']);

    actingAs($staff, 'staff');

    get(route('admin.canned-responses.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CannedResponses/Edit')
            ->where('cannedResponse', null)
            ->has('departments', 2)
            ->where('departments.0.name', 'Sales')
            ->where('departments.1.name', 'Support')
        );

    actingAs($staff, 'staff');

    get(route('admin.canned-responses.edit', $cannedResponse))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CannedResponses/Edit')
            ->where('cannedResponse.title', 'Password Reset')
            ->where('cannedResponse.dept_id', $support->id)
            ->where('departments.0.name', 'Sales')
            ->where('departments.1.name', 'Support')
        );
});

it('creates a canned response and writes an audit log', function (): void {
    $department = Department::query()->create(['name' => 'Support']);
    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.create']);

    actingAs($staff, 'staff');

    post(route('admin.canned-responses.store'), [
        'title' => 'Password Reset',
        'response' => 'Reset your password using the link provided.',
        'notes' => 'Used by support',
        'dept_id' => $department->getKey(),
        'isactive' => true,
    ])->assertRedirect();

    $cannedResponse = CannedResponse::query()->where('title', 'Password Reset')->firstOrFail();
    $cannedResponse->load('department');

    assertDatabaseHas('canned_response', [
        'canned_id' => $cannedResponse->getKey(),
        'title' => 'Password Reset',
        'dept_id' => $department->getKey(),
        'isactive' => 1,
    ], 'legacy');

    assertAuditLogged(
        'canned_response.create',
        $cannedResponse,
        null,
        cannedResponseAuditPayload($cannedResponse, 'Support'),
    );
});

it('rejects invalid canned response creation payloads', function (): void {
    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.create']);

    actingAs($staff, 'staff');

    from(route('admin.canned-responses.create'))
        ->post(route('admin.canned-responses.store'), [
            'title' => '',
            'response' => '',
            'notes' => str_repeat('a', 256),
            'dept_id' => 9999,
            'isactive' => 'invalid',
        ])
        ->assertSessionHasErrors(['title', 'response', 'notes', 'dept_id', 'isactive']);

    expect(CannedResponse::query()->count())->toBe(0);
});

it('forbids unauthorized canned response creation', function (): void {
    actingAsAgent();

    post(route('admin.canned-responses.store'), [
        'title' => 'Password Reset',
        'response' => 'Reset instructions',
        'isactive' => true,
    ])->assertForbidden();
});

it('updates a canned response and writes an audit log diff', function (): void {
    $support = Department::query()->create(['name' => 'Support']);
    $billing = Department::query()->create(['name' => 'Billing']);
    $cannedResponse = CannedResponse::query()->create([
        'dept_id' => $support->getKey(),
        'title' => 'Password Reset',
        'response' => 'Old response',
        'notes' => 'Old notes',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);
    $cannedResponse->load('department');

    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.update']);

    actingAs($staff, 'staff');

    put(route('admin.canned-responses.update', $cannedResponse), [
        'title' => 'Billing Follow-up',
        'response' => 'Updated response',
        'notes' => 'Updated notes',
        'dept_id' => $billing->getKey(),
        'isactive' => false,
    ])->assertRedirect(route('admin.canned-responses.edit', $cannedResponse));

    $before = cannedResponseAuditPayload($cannedResponse, 'Support');

    $cannedResponse->refresh()->load('department');

    expect($cannedResponse->title)->toBe('Billing Follow-up')
        ->and($cannedResponse->response)->toBe('Updated response')
        ->and($cannedResponse->notes)->toBe('Updated notes')
        ->and((int) $cannedResponse->dept_id)->toBe($billing->id)
        ->and((int) $cannedResponse->isactive)->toBe(0);

    assertAuditLogged(
        'canned_response.update',
        $cannedResponse,
        $before,
        cannedResponseAuditPayload($cannedResponse, 'Billing'),
    );
});

it('deletes a canned response and writes an audit log entry', function (): void {
    $department = Department::query()->create(['name' => 'Support']);
    $cannedResponse = CannedResponse::query()->create([
        'dept_id' => $department->getKey(),
        'title' => 'Password Reset',
        'response' => 'Reset response',
        'notes' => 'Used by support',
        'isactive' => 1,
        'created' => now(),
        'updated' => now(),
    ]);
    $cannedResponse->load('department');

    $staff = grantCannedPermissions(actingAsAdmin(), ['admin.canned.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.canned-responses.destroy', $cannedResponse))
        ->assertRedirect(route('admin.canned-responses.index'));

    assertDatabaseMissing('canned_response', ['canned_id' => $cannedResponse->getKey()], 'legacy');

    assertAuditLogged(
        'canned_response.delete',
        $cannedResponse,
        cannedResponseAuditPayload($cannedResponse, 'Support'),
        null,
    );
});
