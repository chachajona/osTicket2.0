# Phase 2a — Cross-cutting Infra + Role Surface — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up Phase 2a cross-cutting admin infrastructure (audit log, permission catalog, admin entry middleware, per-dept role resolver, admin route group, shared Inertia admin shell) and ship the Role admin surface as the first end-to-end exercise of every pattern.

**Architecture:** New `Admin/` namespace mirroring the existing `Scp/` conventions. A `scp_admin_audit_log` table on the `osticket2` connection captures before/after diffs from a service-layer `AuditLogger`. Authorization is two-layered: `EnsureAdminAccess` middleware gates the `/admin` prefix on `admin.access`, while per-action gates (`can:admin.role.create`) are enforced via Policy classes. Per-department role overrides are served by a `DepartmentRoleResolver` plus a `can-in-department` Gate. Role CRUD is the first surface, exercising permission-grid editing, sub-table diff capture, and the FormRequest + Service + Controller + Inertia layout patterns.

**Tech Stack:** Laravel 13, Pest 4, Spatie Laravel Permission v7, Inertia.js v2 + React, shadcn/ui components, MySQL/SQLite (test fixture), TypeScript.

---

## Spec reference

- Source spec: `docs/superpowers/specs/2026-05-01-phase2a-admin-design.md`
- Roadmap context: `.context/plan.md`
- This plan covers spec sub-projects **0 (cross-cutting infra)** and **1 (Role surface)**. Subsequent surfaces (Canned, SLA, Team, Department, Help Topic, Filter, Email Config, Staff) and the migration tool get their own plans.

## File structure

### New files

**Backend:**
- `database/migrations/<timestamp>_create_admin_audit_log_table.php` — creates `scp_admin_audit_log` on connection `osticket2`.
- `database/seeders/PermissionCatalogSeeder.php` — seeds the full Spatie permission catalog.
- `app/Models/Admin/AdminAuditLog.php` — Eloquent model on `osticket2` connection.
- `app/Services/Admin/AuditLogger.php` — service-layer audit writer.
- `app/Services/Admin/DepartmentRoleResolver.php` — per-dept role resolver.
- `app/Services/Admin/RoleService.php` — Role CRUD + audit log integration.
- `app/Http/Middleware/EnsureAdminAccess.php` — `/admin` entry gate.
- `app/Http/Requests/Admin/BaseAdminRequest.php` — abstract base FormRequest.
- `app/Http/Requests/Admin/Role/StoreRoleRequest.php`, `UpdateRoleRequest.php`.
- `app/Policies/Admin/RolePolicy.php` — per-action authorization.
- `app/Http/Controllers/Admin/RoleController.php` — Role surface controller.
- `app/Concerns/HasDepartmentAuthorization.php` — Staff trait providing `canInDept()` / `canForTicket()`.

**Frontend:**
- `resources/js/components/admin/AdminLayout.tsx` — shell with nav + outlet.
- `resources/js/components/admin/FormGrid.tsx` — two-column form layout.
- `resources/js/components/admin/FormSection.tsx` — labeled section wrapper.
- `resources/js/components/admin/PermissionMatrix.tsx` — permission grid editor grouped by category.
- `resources/js/components/admin/ConfirmDialog.tsx` — shared destructive-action confirmation.
- `resources/js/Pages/Admin/Roles/Index.tsx` — list page.
- `resources/js/Pages/Admin/Roles/Edit.tsx` — create+edit page.

**Tests:**
- `tests/Unit/Admin/AuditLoggerTest.php`
- `tests/Unit/Admin/DepartmentRoleResolverTest.php`
- `tests/Unit/Admin/RoleServiceTest.php`
- `tests/Feature/Admin/AdminAccessGateTest.php`
- `tests/Feature/Admin/PermissionCatalogSeederTest.php`
- `tests/Feature/Admin/RoleAdminTest.php`

### Modified files

- `bootstrap/app.php` — register `admin.access` middleware alias.
- `routes/web.php` — add `admin.` route group and Role routes.
- `app/Models/Staff.php` — apply `HasDepartmentAuthorization` trait.
- `database/seeders/DatabaseSeeder.php` — call `PermissionCatalogSeeder`.
- `app/Providers/AuthServiceProvider.php` — register `RolePolicy` and `can-in-department` gate (create the provider if not present).

---

## Conventions used by this plan

- **Test framework:** Pest 4 with Laravel plugin. Tests follow the existing pattern in `tests/Feature/Scp/*` and `tests/Pest.php`. Most feature tests use a real `osticket2` SQLite connection seeded per-test; the `legacy` SQLite fixture provides legacy-shape tables for `Staff` lookups.
- **Spatie:** All Spatie role/permission writes go through `Spatie\Permission\Models\Role`/`Permission` (the project subclasses these as `LegacyRole`/`LegacyPermission` configured for `osticket2` — confirm before each Spatie operation).
- **Staff guard:** `auth:staff`. In Pest, use `actingAs($staff, 'staff')` for HTTP tests, `Auth::guard('staff')->login($staff)` for unit-level scope tests.
- **Test data helpers:** Reuse `scpStaff()` from `tests/Feature/Scp/ScpFoundationTest.php` patterns where helpful; otherwise inline DB inserts to the relevant connection.
- **Commit style:** `feat(admin):`, `feat(admin/role):`, `test(admin):` prefixes. One commit per task.
- **Branching:** All work lands on the active branch `chachajona/phase2-admin`. No sub-branches per task.
- **Connection naming:** `osticket2` is the new app's connection (Spatie tables, audit log, prefs). `legacy` is the legacy data connection — read-only for this plan; written to only post-migration.

---

## Task list

### Task 1: Create the `scp_admin_audit_log` migration

**Files:**
- Create: `database/migrations/<timestamp>_create_admin_audit_log_table.php`

- [ ] **Step 1: Generate migration file**

Run: `php artisan make:migration create_admin_audit_log_table`

The artisan command will produce `database/migrations/<today>_create_admin_audit_log_table.php`. Replace its body with the content in Step 2.

- [ ] **Step 2: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('osticket2')->create('admin_audit_log', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'subj_idx');
            $table->index(['actor_id', 'created_at'], 'actor_idx');
            $table->index(['action', 'created_at'], 'action_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('osticket2')->dropIfExists('admin_audit_log');
    }
};
```

Note the table name is `admin_audit_log`; the `osticket2` connection has prefix `scp_`, so the physical table is `scp_admin_audit_log`.

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`

Expected: A migration line ending in `... DONE` for the new file. No errors.

- [ ] **Step 4: Verify table exists**

Run: `php artisan tinker --execute="echo Schema::connection('osticket2')->hasTable('admin_audit_log') ? 'yes' : 'no';"`

Expected: `yes`

- [ ] **Step 5: Commit**

```bash
git add database/migrations
git commit -m "feat(admin): add scp_admin_audit_log table"
```

---

### Task 2: Create the `AdminAuditLog` Eloquent model

**Files:**
- Create: `app/Models/Admin/AdminAuditLog.php`
- Test: `tests/Unit/Admin/AuditLoggerTest.php` (placeholder; full content lands in Task 3)

- [ ] **Step 1: Write the failing test for the model's casts and connection**

Create `tests/Unit/Admin/AdminAuditLogModelTest.php`:

```php
<?php

use App\Models\Admin\AdminAuditLog;
use Illuminate\Support\Facades\DB;

test('admin audit log model uses osticket2 connection', function () {
    $model = new AdminAuditLog();
    expect($model->getConnectionName())->toBe('osticket2');
    expect($model->getTable())->toBe('admin_audit_log');
});

test('admin audit log casts before, after, metadata as arrays', function () {
    $log = AdminAuditLog::create([
        'actor_id' => 1,
        'action' => 'role.update',
        'subject_type' => 'Role',
        'subject_id' => 7,
        'before' => ['name' => 'A'],
        'after' => ['name' => 'B'],
        'metadata' => ['request_id' => 'abc'],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
    ]);

    $reloaded = AdminAuditLog::find($log->id);

    expect($reloaded->before)->toBe(['name' => 'A'])
        ->and($reloaded->after)->toBe(['name' => 'B'])
        ->and($reloaded->metadata)->toBe(['request_id' => 'abc']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Admin/AdminAuditLogModelTest.php`

Expected: FAIL with `Class "App\Models\Admin\AdminAuditLog" not found`.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'admin_audit_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AdminAuditLog $log): void {
            if (! $log->created_at) {
                $log->created_at = now();
            }
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Admin/AdminAuditLogModelTest.php`

Expected: PASS, 2 tests, ~6 assertions.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Admin/AdminAuditLog.php tests/Unit/Admin/AdminAuditLogModelTest.php
git commit -m "feat(admin): add AdminAuditLog model with JSON casts"
```

---

### Task 3: Build `AuditLogger` service with diff logic

**Files:**
- Create: `app/Services/Admin/AuditLogger.php`
- Test: `tests/Unit/Admin/AuditLoggerTest.php`

- [ ] **Step 1: Write the failing tests covering create / update / delete / exclusion**

Create `tests/Unit/Admin/AuditLoggerTest.php`:

```php
<?php

use App\Models\Admin\AdminAuditLog;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FakeAuditedSubject extends Model
{
    protected $connection = 'osticket2';
    protected $table = 'fake_audit_subject';
    protected $guarded = [];
    public $incrementing = true;
    protected $primaryKey = 'id';

    protected static array $auditExcluded = ['password'];

    public static function getAuditExcluded(): array
    {
        return static::$auditExcluded;
    }
}

class FakeActor
{
    public int $staff_id = 42;
    public function getKey(): int { return $this->staff_id; }
}

beforeEach(function (): void {
    \Illuminate\Support\Facades\Schema::connection('osticket2')->create('fake_audit_subject', function ($table): void {
        $table->bigIncrements('id');
        $table->string('name')->nullable();
        $table->string('password')->nullable();
    });
});

afterEach(function (): void {
    \Illuminate\Support\Facades\Schema::connection('osticket2')->dropIfExists('fake_audit_subject');
});

test('record() with before=null and after captures a create event', function () {
    $subject = FakeAuditedSubject::create(['name' => 'Manager']);
    $logger = app(AuditLogger::class);
    $actor = new FakeActor();

    $request = Request::create('/admin/roles', 'POST', server: ['HTTP_USER_AGENT' => 'phpunit', 'REMOTE_ADDR' => '10.0.0.1']);
    app()->instance('request', $request);

    $logger->record($actor, 'role.create', $subject, before: null, after: $subject->only(['name']));

    $log = AdminAuditLog::first();
    expect($log->action)->toBe('role.create')
        ->and($log->actor_id)->toBe(42)
        ->and($log->subject_type)->toBe(class_basename(FakeAuditedSubject::class))
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->before)->toBeNull()
        ->and($log->after)->toBe(['name' => 'Manager'])
        ->and($log->ip_address)->toBe('10.0.0.1')
        ->and($log->user_agent)->toBe('phpunit');
});

test('record() with before and after captures a diff for update', function () {
    $subject = FakeAuditedSubject::create(['name' => 'Old']);
    $logger = app(AuditLogger::class);
    $actor = new FakeActor();

    $logger->record($actor, 'role.update', $subject,
        before: ['name' => 'Old'],
        after:  ['name' => 'New'],
    );

    $log = AdminAuditLog::first();
    expect($log->before)->toBe(['name' => 'Old'])
        ->and($log->after)->toBe(['name' => 'New']);
});

test('record() with after=null captures a delete event', function () {
    $subject = FakeAuditedSubject::create(['name' => 'Doomed']);
    $logger = app(AuditLogger::class);
    $actor = new FakeActor();

    $logger->record($actor, 'role.delete', $subject,
        before: ['name' => 'Doomed'],
        after:  null,
    );

    $log = AdminAuditLog::first();
    expect($log->before)->toBe(['name' => 'Doomed'])
        ->and($log->after)->toBeNull();
});

test('record() redacts excluded fields based on subject model', function () {
    $subject = FakeAuditedSubject::create(['name' => 'Bob', 'password' => 'secret']);
    $logger = app(AuditLogger::class);
    $actor = new FakeActor();

    $logger->record($actor, 'staff.update', $subject,
        before: ['name' => 'Bob', 'password' => 'old-secret'],
        after:  ['name' => 'Bob', 'password' => 'new-secret'],
    );

    $log = AdminAuditLog::first();
    expect($log->before)->toBe(['name' => 'Bob', 'password' => '[redacted]'])
        ->and($log->after)->toBe(['name' => 'Bob', 'password' => '[redacted]']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Admin/AuditLoggerTest.php`

Expected: FAIL with `Class "App\Services\Admin\AuditLogger" not found`.

- [ ] **Step 3: Write `AuditLogger`**

```php
<?php

namespace App\Services\Admin;

use App\Models\Admin\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function record(
        object $actor,
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
    ): AdminAuditLog {
        $excluded = $this->excludedFields($subject);

        return AdminAuditLog::create([
            'actor_id' => method_exists($actor, 'getKey') ? $actor->getKey() : ($actor->staff_id ?? $actor->id ?? 0),
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'before' => $before === null ? null : $this->redact($before, $excluded),
            'after' => $after === null ? null : $this->redact($after, $excluded),
            'metadata' => $metadata,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255),
        ]);
    }

    private function excludedFields(Model $subject): array
    {
        if (method_exists($subject, 'getAuditExcluded')) {
            return $subject::getAuditExcluded();
        }

        return [];
    }

    private function redact(array $payload, array $excluded): array
    {
        foreach ($excluded as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Admin/AuditLoggerTest.php`

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Admin/AuditLogger.php tests/Unit/Admin/AuditLoggerTest.php
git commit -m "feat(admin): add AuditLogger service with diff + redaction"
```

---

### Task 4: Seed the Spatie permission catalog

**Files:**
- Create: `database/seeders/PermissionCatalogSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Admin/PermissionCatalogSeederTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PermissionCatalogSeederTest.php`:

```php
<?php

use App\Models\LegacyPermission;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder creates the full ticket permission group', function () {
    (new PermissionCatalogSeeder())->run();

    $names = LegacyPermission::pluck('name')->all();
    foreach (['ticket.create', 'ticket.edit', 'ticket.assign', 'ticket.transfer', 'ticket.reply', 'ticket.close', 'ticket.delete', 'ticket.release', 'ticket.markanswered'] as $key) {
        expect($names)->toContain($key);
    }
});

test('seeder creates admin-level permissions for every Phase 2a surface', function () {
    (new PermissionCatalogSeeder())->run();

    $names = LegacyPermission::pluck('name')->all();
    expect($names)->toContain('admin.access');

    foreach (['role', 'staff', 'department', 'team', 'sla', 'canned', 'helptopic', 'filter', 'email'] as $surface) {
        foreach (['create', 'update', 'delete'] as $action) {
            expect($names)->toContain("admin.{$surface}.{$action}");
        }
    }
});

test('seeder is idempotent', function () {
    (new PermissionCatalogSeeder())->run();
    $first = LegacyPermission::count();

    (new PermissionCatalogSeeder())->run();
    $second = LegacyPermission::count();

    expect($first)->toBe($second);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Admin/PermissionCatalogSeederTest.php`

Expected: FAIL with `Class "Database\Seeders\PermissionCatalogSeeder" not found`.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\LegacyPermission;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $key) {
            LegacyPermission::firstOrCreate(
                ['name' => $key, 'guard_name' => 'staff'],
            );
        }
    }

    /**
     * Canonical permission catalog. Keys preserved exactly from legacy where applicable;
     * `admin.*` entries are new for Phase 2a admin surfaces.
     */
    public function catalog(): array
    {
        return [
            // Tickets
            'ticket.create', 'ticket.edit', 'ticket.assign', 'ticket.transfer',
            'ticket.reply', 'ticket.close', 'ticket.delete', 'ticket.release', 'ticket.markanswered',

            // Tasks
            'task.create', 'task.edit', 'task.assign', 'task.transfer',
            'task.reply', 'task.close', 'task.delete',

            // Users
            'user.create', 'user.edit', 'user.delete', 'user.manage', 'user.dir',

            // Organizations
            'org.create', 'org.edit', 'org.delete',

            // Knowledgebase
            'kb.premade', 'kb.faq',

            // Visibility
            'visibility.agents', 'visibility.departments', 'visibility.private',

            // Admin (new in Phase 2a)
            'admin.access',
            'admin.role.create', 'admin.role.update', 'admin.role.delete',
            'admin.staff.create', 'admin.staff.update', 'admin.staff.delete',
            'admin.department.create', 'admin.department.update', 'admin.department.delete',
            'admin.team.create', 'admin.team.update', 'admin.team.delete',
            'admin.sla.create', 'admin.sla.update', 'admin.sla.delete',
            'admin.canned.create', 'admin.canned.update', 'admin.canned.delete',
            'admin.helptopic.create', 'admin.helptopic.update', 'admin.helptopic.delete',
            'admin.filter.create', 'admin.filter.update', 'admin.filter.delete',
            'admin.email.create', 'admin.email.update', 'admin.email.delete',
        ];
    }
}
```

- [ ] **Step 4: Wire into `DatabaseSeeder`**

Open `database/seeders/DatabaseSeeder.php` and add a call inside `run()`:

```php
$this->call(PermissionCatalogSeeder::class);
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/pest tests/Feature/Admin/PermissionCatalogSeederTest.php`

Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add database/seeders tests/Feature/Admin/PermissionCatalogSeederTest.php
git commit -m "feat(admin): seed Spatie permission catalog"
```

---

### Task 5: `EnsureAdminAccess` middleware

**Files:**
- Create: `app/Http/Middleware/EnsureAdminAccess.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Admin/AdminAccessGateTest.php`

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Admin/AdminAccessGateTest.php`:

```php
<?php

use App\Models\LegacyPermission;
use App\Models\LegacyRole;
use App\Models\Staff;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new PermissionCatalogSeeder())->run();

    Route::middleware(['web', 'auth.staff', 'admin.access'])
        ->get('/__test/admin-only', fn () => response('ok'));
});

function adminGateStaff(array $attributes = []): Staff
{
    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => 70,
        'dept_id' => 1,
        'username' => 'admingate',
        'firstname' => 'Admin',
        'lastname' => 'Gate',
        'email' => 'admingate@example.com',
        'passwd' => Hash::make('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->find($attributes['staff_id'] ?? 70);
}

test('unauthenticated request to admin route redirects', function () {
    $response = $this->get('/__test/admin-only');
    $response->assertRedirect();
});

test('authenticated staff without admin.access gets 403', function () {
    $staff = adminGateStaff(['staff_id' => 71]);
    $response = $this->actingAs($staff, 'staff')->get('/__test/admin-only');
    $response->assertForbidden();
});

test('authenticated staff with admin.access passes', function () {
    $staff = adminGateStaff(['staff_id' => 72]);

    $role = LegacyRole::firstOrCreate(['name' => 'AdminGate', 'guard_name' => 'staff']);
    $role->givePermissionTo('admin.access');
    $staff->assignRole($role);

    $response = $this->actingAs($staff, 'staff')->get('/__test/admin-only');
    $response->assertOk();
    $response->assertSee('ok');
});
```

- [ ] **Step 2: Run tests to confirm failures**

Run: `vendor/bin/pest tests/Feature/Admin/AdminAccessGateTest.php`

Expected: FAIL — middleware alias `admin.access` not registered (or class not found).

- [ ] **Step 3: Write the middleware**

Create `app/Http/Middleware/EnsureAdminAccess.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');

        if (! $staff) {
            return redirect()->guest(route('login'));
        }

        if (! $staff->can('admin.access')) {
            abort(403, 'Admin access not granted.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the alias in `bootstrap/app.php`**

Inside the `$middleware->alias([...])` block, add:

```php
'admin.access' => \App\Http\Middleware\EnsureAdminAccess::class,
```

- [ ] **Step 5: Run tests to verify passes**

Run: `vendor/bin/pest tests/Feature/Admin/AdminAccessGateTest.php`

Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureAdminAccess.php bootstrap/app.php tests/Feature/Admin/AdminAccessGateTest.php
git commit -m "feat(admin): add EnsureAdminAccess middleware"
```

---

### Task 6: `DepartmentRoleResolver` service

**Files:**
- Create: `app/Services/Admin/DepartmentRoleResolver.php`
- Test: `tests/Unit/Admin/DepartmentRoleResolverTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Admin/DepartmentRoleResolverTest.php`:

```php
<?php

use App\Models\LegacyRole;
use App\Models\Staff;
use App\Services\Admin\DepartmentRoleResolver;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new PermissionCatalogSeeder())->run();
});

function resolverStaff(array $attributes = []): Staff
{
    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => 80,
        'dept_id' => 1,
        'username' => 'resolver',
        'firstname' => 'R',
        'lastname' => 'S',
        'email' => 'resolver@example.com',
        'passwd' => Hash::make('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->find($attributes['staff_id'] ?? 80);
}

test('resolver returns primary role when no override exists', function () {
    $staff = resolverStaff(['staff_id' => 81]);
    $primary = LegacyRole::firstOrCreate(['name' => 'Agent', 'guard_name' => 'staff']);
    $staff->assignRole($primary);

    $resolver = new DepartmentRoleResolver();
    $role = $resolver->roleForDepartment($staff, 1);

    expect($role?->name)->toBe('Agent');
});

test('resolver returns override role when staff_dept_access has one', function () {
    $staff = resolverStaff(['staff_id' => 82]);
    $primary = LegacyRole::firstOrCreate(['name' => 'Agent', 'guard_name' => 'staff']);
    $manager = LegacyRole::firstOrCreate(['name' => 'Manager', 'guard_name' => 'staff']);
    $staff->assignRole($primary);

    DB::connection('legacy')->table('staff_dept_access')->insert([
        'staff_id' => 82,
        'dept_id' => 5,
        'role_id' => $manager->id,
        'flags' => 0,
    ]);

    $resolver = new DepartmentRoleResolver();
    expect($resolver->roleForDepartment($staff, 5)?->name)->toBe('Manager');
    expect($resolver->roleForDepartment($staff, 1)?->name)->toBe('Agent');
});

test('resolver falls back to primary role if override role_id is missing', function () {
    $staff = resolverStaff(['staff_id' => 83]);
    $primary = LegacyRole::firstOrCreate(['name' => 'Agent', 'guard_name' => 'staff']);
    $staff->assignRole($primary);

    DB::connection('legacy')->table('staff_dept_access')->insert([
        'staff_id' => 83,
        'dept_id' => 9,
        'role_id' => 99999, // intentionally non-existent
        'flags' => 0,
    ]);

    $resolver = new DepartmentRoleResolver();
    expect($resolver->roleForDepartment($staff, 9)?->name)->toBe('Agent');
});

test('resolver returns null if staff has no roles at all', function () {
    $staff = resolverStaff(['staff_id' => 84]);
    $resolver = new DepartmentRoleResolver();
    expect($resolver->roleForDepartment($staff, 1))->toBeNull();
});
```

- [ ] **Step 2: Run tests to confirm failure**

Run: `vendor/bin/pest tests/Unit/Admin/DepartmentRoleResolverTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Write the resolver**

Create `app/Services/Admin/DepartmentRoleResolver.php`:

```php
<?php

namespace App\Services\Admin;

use App\Models\LegacyRole;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class DepartmentRoleResolver
{
    public function roleForDepartment(Staff $staff, int $deptId): ?LegacyRole
    {
        $row = DB::connection('legacy')
            ->table('staff_dept_access')
            ->where('staff_id', $staff->getKey())
            ->where('dept_id', $deptId)
            ->first();

        if ($row && $role = LegacyRole::find($row->role_id)) {
            return $role;
        }

        return $staff->roles()->first();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/Admin/DepartmentRoleResolverTest.php`

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Admin/DepartmentRoleResolver.php tests/Unit/Admin/DepartmentRoleResolverTest.php
git commit -m "feat(admin): add DepartmentRoleResolver"
```

---

### Task 7: `can-in-department` Gate + Staff trait

**Files:**
- Create: `app/Concerns/HasDepartmentAuthorization.php`
- Create or modify: `app/Providers/AuthServiceProvider.php` (create if absent)
- Modify: `app/Models/Staff.php`
- Test: extends `tests/Unit/Admin/DepartmentRoleResolverTest.php` with new tests below

- [ ] **Step 1: Append tests for the trait**

Add to `tests/Unit/Admin/DepartmentRoleResolverTest.php`:

```php
test('staff->canInDept uses resolver to evaluate permissions', function () {
    $staff = resolverStaff(['staff_id' => 85]);
    $primary = LegacyRole::firstOrCreate(['name' => 'Agent', 'guard_name' => 'staff']);
    $manager = LegacyRole::firstOrCreate(['name' => 'Manager', 'guard_name' => 'staff']);
    $manager->givePermissionTo('ticket.assign');
    $staff->assignRole($primary);

    DB::connection('legacy')->table('staff_dept_access')->insert([
        'staff_id' => 85,
        'dept_id' => 7,
        'role_id' => $manager->id,
        'flags' => 0,
    ]);

    expect($staff->canInDept('ticket.assign', 7))->toBeTrue();
    expect($staff->canInDept('ticket.assign', 1))->toBeFalse();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `vendor/bin/pest tests/Unit/Admin/DepartmentRoleResolverTest.php`

Expected: FAIL — `Method canInDept does not exist on App\Models\Staff`.

- [ ] **Step 3: Create the trait**

Create `app/Concerns/HasDepartmentAuthorization.php`:

```php
<?php

namespace App\Concerns;

use App\Services\Admin\DepartmentRoleResolver;
use Illuminate\Support\Facades\App;

trait HasDepartmentAuthorization
{
    public function canInDept(string $permission, int $deptId): bool
    {
        $role = App::make(DepartmentRoleResolver::class)->roleForDepartment($this, $deptId);

        return $role?->hasPermissionTo($permission) ?? false;
    }

    public function canForTicket(string $permission, $ticket): bool
    {
        $deptId = is_int($ticket) ? $ticket : (int) ($ticket->dept_id ?? 0);

        return $this->canInDept($permission, $deptId);
    }
}
```

- [ ] **Step 4: Apply the trait to `Staff`**

Open `app/Models/Staff.php` and:

1. Add `use App\Concerns\HasDepartmentAuthorization;` to the imports.
2. Add `use HasDepartmentAuthorization;` inside the class body alongside the existing trait list.

- [ ] **Step 5: Define the `can-in-department` Gate (defensive, in case any code path uses `Gate::check('can-in-department', ...)` instead of the trait)**

Check whether `app/Providers/AuthServiceProvider.php` exists. If not, generate it:

Run: `php artisan make:provider AuthServiceProvider`

Inside `AuthServiceProvider::boot()`, add:

```php
\Illuminate\Support\Facades\Gate::define('can-in-department', function ($user, string $permission, int $deptId) {
    if (! method_exists($user, 'canInDept')) {
        return false;
    }
    return $user->canInDept($permission, $deptId);
});
```

If you generated a new provider, register it in `bootstrap/providers.php`.

- [ ] **Step 6: Run tests**

Run: `vendor/bin/pest tests/Unit/Admin/DepartmentRoleResolverTest.php`

Expected: PASS, 5 tests total.

- [ ] **Step 7: Commit**

```bash
git add app/Concerns/HasDepartmentAuthorization.php app/Providers/AuthServiceProvider.php app/Models/Staff.php bootstrap/providers.php tests/Unit/Admin/DepartmentRoleResolverTest.php
git commit -m "feat(admin): add per-dept authorization trait and gate"
```

---

### Task 8: Admin route group scaffolding

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing test (also covers index placeholder)**

Append to `tests/Feature/Admin/AdminAccessGateTest.php`:

```php
test('GET /admin redirects unauthenticated users', function () {
    $this->get('/admin')->assertRedirect();
});

test('GET /admin returns 403 without admin.access', function () {
    $staff = adminGateStaff(['staff_id' => 73]);
    $this->actingAs($staff, 'staff')->get('/admin')->assertForbidden();
});
```

- [ ] **Step 2: Run tests — expect 404 (route undefined) which is a failure**

Run: `vendor/bin/pest tests/Feature/Admin/AdminAccessGateTest.php`

Expected: FAIL — `/admin` returns 404 because the route doesn't exist yet.

- [ ] **Step 3: Add the admin route group to `routes/web.php`**

Append:

```php
Route::middleware(['auth.staff', 'admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', function () {
            return inertia('Admin/Dashboard');
        })->name('dashboard');
    });
```

(`Admin/Dashboard.tsx` does not exist yet; for the auth/redirect tests this is fine, the response is generated before Inertia renders. We'll add the page later if needed; alternatively, return `response('Admin OK')` here as a placeholder until the dashboard page is built — keep this simple for now.)

For test stability, replace the closure with:

```php
Route::get('/', fn () => response('Admin OK'))->name('dashboard');
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Feature/Admin/AdminAccessGateTest.php`

Expected: PASS, 5 tests total.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php tests/Feature/Admin/AdminAccessGateTest.php
git commit -m "feat(admin): add /admin route group with entry gate"
```

---

### Task 9: Base FormRequest

**Files:**
- Create: `app/Http/Requests/Admin/BaseAdminRequest.php`

(No tests — abstract base. Tested implicitly through Role-specific requests in later tasks.)

- [ ] **Step 1: Create the abstract class**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('staff')?->can('admin.access') ?? false;
    }

    /**
     * Subclasses MUST override.
     */
    abstract public function rules(): array;

    public function actor()
    {
        return $this->user('staff');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/Admin/BaseAdminRequest.php
git commit -m "feat(admin): add BaseAdminRequest"
```

---

### Task 10: `RolePolicy`

**Files:**
- Create: `app/Policies/Admin/RolePolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Test: `tests/Feature/Admin/RoleAdminTest.php` (created in Task 13; policy gets a small unit test now)

- [ ] **Step 1: Write a small policy unit test**

Create `tests/Unit/Admin/RolePolicyTest.php`:

```php
<?php

use App\Models\LegacyRole;
use App\Models\Staff;
use App\Policies\Admin\RolePolicy;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new PermissionCatalogSeeder())->run();
});

function policyStaff(int $id, array $perms = []): Staff
{
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => $id,
        'dept_id' => 1,
        'username' => "policy{$id}",
        'firstname' => 'P',
        'lastname' => 'S',
        'email' => "policy{$id}@example.com",
        'passwd' => Hash::make('p'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);
    $staff = Staff::on('legacy')->find($id);

    if ($perms) {
        $role = LegacyRole::firstOrCreate(['name' => "Role{$id}", 'guard_name' => 'staff']);
        foreach ($perms as $p) {
            $role->givePermissionTo($p);
        }
        $staff->assignRole($role);
    }

    return $staff;
}

test('only admin.role.create grants create permission', function () {
    $policy = new RolePolicy();
    expect($policy->create(policyStaff(91)))->toBeFalse();
    expect($policy->create(policyStaff(92, ['admin.role.create'])))->toBeTrue();
});

test('only admin.role.update grants update permission', function () {
    $policy = new RolePolicy();
    $role = LegacyRole::firstOrCreate(['name' => 'Target', 'guard_name' => 'staff']);
    expect($policy->update(policyStaff(93), $role))->toBeFalse();
    expect($policy->update(policyStaff(94, ['admin.role.update']), $role))->toBeTrue();
});

test('only admin.role.delete grants delete permission', function () {
    $policy = new RolePolicy();
    $role = LegacyRole::firstOrCreate(['name' => 'Target', 'guard_name' => 'staff']);
    expect($policy->delete(policyStaff(95), $role))->toBeFalse();
    expect($policy->delete(policyStaff(96, ['admin.role.delete']), $role))->toBeTrue();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `vendor/bin/pest tests/Unit/Admin/RolePolicyTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Create the policy**

```php
<?php

namespace App\Policies\Admin;

use App\Models\LegacyRole;
use App\Models\Staff;

class RolePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('admin.access');
    }

    public function view(Staff $staff, LegacyRole $role): bool
    {
        return $staff->can('admin.access');
    }

    public function create(Staff $staff): bool
    {
        return $staff->can('admin.role.create');
    }

    public function update(Staff $staff, LegacyRole $role): bool
    {
        return $staff->can('admin.role.update');
    }

    public function delete(Staff $staff, LegacyRole $role): bool
    {
        return $staff->can('admin.role.delete');
    }
}
```

- [ ] **Step 4: Register the policy**

In `app/Providers/AuthServiceProvider.php`'s `boot()` method, add:

```php
\Illuminate\Support\Facades\Gate::policy(\App\Models\LegacyRole::class, \App\Policies\Admin\RolePolicy::class);
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/pest tests/Unit/Admin/RolePolicyTest.php`

Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/Admin app/Providers/AuthServiceProvider.php tests/Unit/Admin/RolePolicyTest.php
git commit -m "feat(admin/role): add RolePolicy"
```

---

### Task 11: Role FormRequests

**Files:**
- Create: `app/Http/Requests/Admin/Role/StoreRoleRequest.php`
- Create: `app/Http/Requests/Admin/Role/UpdateRoleRequest.php`

(Validation rules are exercised through feature tests in Task 13.)

- [ ] **Step 1: Create `StoreRoleRequest`**

```php
<?php

namespace App\Http\Requests\Admin\Role;

use App\Http\Requests\Admin\BaseAdminRequest;

class StoreRoleRequest extends BaseAdminRequest
{
    public function authorize(): bool
    {
        return $this->user('staff')?->can('admin.role.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'unique:osticket2.roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:osticket2.permissions,name'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

> **Note on `unique:osticket2.roles,name`:** Spatie's tables live on the `osticket2` connection. The `connection.table` qualifier in `unique:` and `exists:` rules tells Laravel which connection to query. Verify the actual table name via `php artisan tinker --execute="echo (new \\App\\Models\\LegacyRole)->getTable();"` before this task — adjust the rule if the table name differs.

- [ ] **Step 2: Create `UpdateRoleRequest`**

```php
<?php

namespace App\Http\Requests\Admin\Role;

use App\Http\Requests\Admin\BaseAdminRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseAdminRequest
{
    public function authorize(): bool
    {
        return $this->user('staff')?->can('admin.role.update') ?? false;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('osticket2.roles', 'name')->ignore($roleId)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:osticket2.permissions,name'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/Admin/Role
git commit -m "feat(admin/role): add Store/UpdateRoleRequest"
```

---

### Task 12: `RoleService` with audit-aware CRUD

**Files:**
- Create: `app/Services/Admin/RoleService.php`
- Test: `tests/Unit/Admin/RoleServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Admin/RoleServiceTest.php`:

```php
<?php

use App\Models\Admin\AdminAuditLog;
use App\Models\LegacyRole;
use App\Services\Admin\RoleService;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new PermissionCatalogSeeder())->run();
});

class FakeRoleActor
{
    public int $staff_id = 100;
    public function getKey(): int { return $this->staff_id; }
}

test('create() persists a role and writes a role.create audit entry', function () {
    $service = app(RoleService::class);
    $actor = new FakeRoleActor();

    $role = $service->create($actor, [
        'name' => 'Manager',
        'permissions' => ['ticket.assign', 'ticket.transfer'],
        'notes' => null,
    ]);

    expect($role->name)->toBe('Manager');
    expect($role->getPermissionNames()->all())->toEqualCanonicalizing(['ticket.assign', 'ticket.transfer']);

    $log = AdminAuditLog::where('action', 'role.create')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe(100)
        ->and($log->subject_id)->toBe($role->id)
        ->and($log->before)->toBeNull()
        ->and($log->after['name'])->toBe('Manager')
        ->and($log->after['permissions'])->toEqualCanonicalizing(['ticket.assign', 'ticket.transfer']);
});

test('update() captures only changed fields in the audit diff', function () {
    $service = app(RoleService::class);
    $actor = new FakeRoleActor();

    $role = $service->create($actor, ['name' => 'Manager', 'permissions' => ['ticket.assign']]);
    AdminAuditLog::query()->delete();

    $service->update($actor, $role, ['name' => 'Senior Manager', 'permissions' => ['ticket.assign']]);

    $log = AdminAuditLog::where('action', 'role.update')->first();
    expect($log->before)->toHaveKey('name', 'Manager')
        ->and($log->after)->toHaveKey('name', 'Senior Manager')
        ->and($log->before)->not->toHaveKey('permissions') // permissions did not change
        ->and($log->after)->not->toHaveKey('permissions');
});

test('update() captures permission grant changes as a synthetic field', function () {
    $service = app(RoleService::class);
    $actor = new FakeRoleActor();

    $role = $service->create($actor, ['name' => 'Manager', 'permissions' => ['ticket.assign']]);
    AdminAuditLog::query()->delete();

    $service->update($actor, $role, ['name' => 'Manager', 'permissions' => ['ticket.assign', 'ticket.reply']]);

    $log = AdminAuditLog::where('action', 'role.update')->first();
    expect($log->before['permissions'])->toEqualCanonicalizing(['ticket.assign'])
        ->and($log->after['permissions'])->toEqualCanonicalizing(['ticket.assign', 'ticket.reply']);
});

test('delete() removes the role and writes a role.delete audit entry', function () {
    $service = app(RoleService::class);
    $actor = new FakeRoleActor();

    $role = $service->create($actor, ['name' => 'Doomed', 'permissions' => []]);
    $roleId = $role->id;
    AdminAuditLog::query()->delete();

    $service->delete($actor, $role);

    expect(LegacyRole::find($roleId))->toBeNull();

    $log = AdminAuditLog::where('action', 'role.delete')->first();
    expect($log->before['name'])->toBe('Doomed')
        ->and($log->after)->toBeNull();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `vendor/bin/pest tests/Unit/Admin/RoleServiceTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\Admin;

use App\Models\LegacyRole;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(object $actor, array $attrs): LegacyRole
    {
        return DB::connection('osticket2')->transaction(function () use ($actor, $attrs) {
            $role = LegacyRole::create([
                'name' => $attrs['name'],
                'guard_name' => 'staff',
            ]);

            $permissions = $attrs['permissions'] ?? [];
            $role->syncPermissions($permissions);

            $this->auditLogger->record(
                actor:   $actor,
                action:  'role.create',
                subject: $role,
                before:  null,
                after:   ['name' => $role->name, 'permissions' => $permissions],
            );

            return $role;
        });
    }

    public function update(object $actor, LegacyRole $role, array $attrs): LegacyRole
    {
        return DB::connection('osticket2')->transaction(function () use ($actor, $role, $attrs) {
            $beforeAttrs = $role->only(array_keys($attrs));
            $beforePermissions = $role->getPermissionNames()->sort()->values()->all();

            $role->fill(['name' => $attrs['name']])->save();

            $afterPermissions = collect($attrs['permissions'] ?? [])->sort()->values()->all();
            $role->syncPermissions($afterPermissions);

            $afterAttrs = $role->fresh()->only(array_keys($attrs));

            // Only include changed fields in the diff
            $before = [];
            $after = [];
            foreach (['name'] as $key) {
                if (($beforeAttrs[$key] ?? null) !== ($afterAttrs[$key] ?? null)) {
                    $before[$key] = $beforeAttrs[$key] ?? null;
                    $after[$key] = $afterAttrs[$key] ?? null;
                }
            }
            if ($beforePermissions !== $afterPermissions) {
                $before['permissions'] = $beforePermissions;
                $after['permissions'] = $afterPermissions;
            }

            $this->auditLogger->record(
                actor:   $actor,
                action:  'role.update',
                subject: $role,
                before:  $before,
                after:   $after,
            );

            return $role->fresh();
        });
    }

    public function delete(object $actor, LegacyRole $role): void
    {
        DB::connection('osticket2')->transaction(function () use ($actor, $role) {
            $beforePermissions = $role->getPermissionNames()->sort()->values()->all();
            $before = ['name' => $role->name, 'permissions' => $beforePermissions];

            // Capture id before delete so audit log subject_id stays meaningful.
            $clone = clone $role;

            $role->delete();

            $this->auditLogger->record(
                actor:   $actor,
                action:  'role.delete',
                subject: $clone,
                before:  $before,
                after:   null,
            );
        });
    }
}
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/Admin/RoleServiceTest.php`

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Admin/RoleService.php tests/Unit/Admin/RoleServiceTest.php
git commit -m "feat(admin/role): add RoleService with audit-aware CRUD"
```

---

### Task 13: `RoleController` + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/Admin/RoleController.php`
- Modify: `routes/web.php` — add Role resource routes inside the `admin.` group.
- Test: `tests/Feature/Admin/RoleAdminTest.php`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Admin/RoleAdminTest.php`:

```php
<?php

use App\Models\Admin\AdminAuditLog;
use App\Models\LegacyRole;
use App\Models\Staff;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new PermissionCatalogSeeder())->run();
});

function roleAdminStaff(int $id, array $perms): Staff
{
    DB::connection('legacy')->table('staff')->insert([
        'staff_id' => $id,
        'dept_id' => 1,
        'username' => "ra{$id}",
        'firstname' => 'R',
        'lastname' => 'A',
        'email' => "ra{$id}@example.com",
        'passwd' => Hash::make('p'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ]);
    $staff = Staff::on('legacy')->find($id);

    $role = LegacyRole::firstOrCreate(['name' => "RA{$id}", 'guard_name' => 'staff']);
    foreach ($perms as $p) {
        $role->givePermissionTo($p);
    }
    $staff->assignRole($role);

    return $staff;
}

test('GET /admin/roles lists roles for authorized staff', function () {
    $staff = roleAdminStaff(110, ['admin.access']);
    LegacyRole::create(['name' => 'Existing', 'guard_name' => 'staff']);

    $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/admin/roles')
        ->assertOk()
        ->assertJsonPath('component', 'Admin/Roles/Index');
});

test('GET /admin/roles/create returns the form for authorized staff', function () {
    $staff = roleAdminStaff(111, ['admin.access', 'admin.role.create']);

    $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/admin/roles/create')
        ->assertOk()
        ->assertJsonPath('component', 'Admin/Roles/Edit');
});

test('POST /admin/roles creates a role and writes audit log', function () {
    $staff = roleAdminStaff(112, ['admin.access', 'admin.role.create']);

    $response = $this->actingAs($staff, 'staff')
        ->post('/admin/roles', [
            'name' => 'NewRole',
            'permissions' => ['ticket.create', 'ticket.reply'],
        ]);

    $response->assertRedirect();
    expect(LegacyRole::where('name', 'NewRole')->exists())->toBeTrue();
    expect(AdminAuditLog::where('action', 'role.create')->where('actor_id', 112)->exists())->toBeTrue();
});

test('POST /admin/roles fails for staff without admin.role.create', function () {
    $staff = roleAdminStaff(113, ['admin.access']);

    $this->actingAs($staff, 'staff')
        ->post('/admin/roles', ['name' => 'X', 'permissions' => []])
        ->assertForbidden();
});

test('PUT /admin/roles/{role} updates a role and writes audit log', function () {
    $staff = roleAdminStaff(114, ['admin.access', 'admin.role.update']);
    $role = LegacyRole::create(['name' => 'Editable', 'guard_name' => 'staff']);

    $response = $this->actingAs($staff, 'staff')
        ->put("/admin/roles/{$role->id}", [
            'name' => 'Edited',
            'permissions' => ['ticket.reply'],
        ]);

    $response->assertRedirect();
    expect(LegacyRole::find($role->id)->name)->toBe('Edited');
    expect(AdminAuditLog::where('action', 'role.update')->where('subject_id', $role->id)->exists())->toBeTrue();
});

test('PUT /admin/roles/{role} fails validation on duplicate name', function () {
    $staff = roleAdminStaff(115, ['admin.access', 'admin.role.update']);
    LegacyRole::create(['name' => 'Taken', 'guard_name' => 'staff']);
    $role = LegacyRole::create(['name' => 'Other', 'guard_name' => 'staff']);

    $this->actingAs($staff, 'staff')
        ->from("/admin/roles/{$role->id}/edit")
        ->put("/admin/roles/{$role->id}", [
            'name' => 'Taken',
            'permissions' => [],
        ])
        ->assertSessionHasErrors('name');
});

test('DELETE /admin/roles/{role} deletes and writes audit log', function () {
    $staff = roleAdminStaff(116, ['admin.access', 'admin.role.delete']);
    $role = LegacyRole::create(['name' => 'Doomed', 'guard_name' => 'staff']);
    $roleId = $role->id;

    $this->actingAs($staff, 'staff')
        ->delete("/admin/roles/{$role->id}")
        ->assertRedirect();

    expect(LegacyRole::find($roleId))->toBeNull();
    expect(AdminAuditLog::where('action', 'role.delete')->where('subject_id', $roleId)->exists())->toBeTrue();
});

test('DELETE /admin/roles/{role} fails for staff without admin.role.delete', function () {
    $staff = roleAdminStaff(117, ['admin.access']);
    $role = LegacyRole::create(['name' => 'Locked', 'guard_name' => 'staff']);

    $this->actingAs($staff, 'staff')
        ->delete("/admin/roles/{$role->id}")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `vendor/bin/pest tests/Feature/Admin/RoleAdminTest.php`

Expected: FAIL — controller not found / routes 404.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Admin/RoleController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Models\LegacyPermission;
use App\Models\LegacyRole;
use App\Services\Admin\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(): Response
    {
        $this->authorize('viewAny', LegacyRole::class);

        $roles = LegacyRole::query()
            ->orderBy('name')
            ->withCount('permissions')
            ->get(['id', 'name'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions_count' => $r->permissions_count,
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', LegacyRole::class);

        return Inertia::render('Admin/Roles/Edit', [
            'mode' => 'create',
            'role' => null,
            'catalog' => $this->permissionCatalog(),
            'granted' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleService->create($request->actor(), $request->validated());

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(LegacyRole $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Admin/Roles/Edit', [
            'mode' => 'edit',
            'role' => ['id' => $role->id, 'name' => $role->name],
            'catalog' => $this->permissionCatalog(),
            'granted' => $role->getPermissionNames()->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, LegacyRole $role): RedirectResponse
    {
        $this->roleService->update($request->actor(), $role, $request->validated());

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(LegacyRole $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $this->roleService->delete(request()->user('staff'), $role);

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /**
     * Returns the permission catalog grouped by category for the UI matrix.
     */
    private function permissionCatalog(): array
    {
        $all = LegacyPermission::query()->orderBy('name')->pluck('name')->all();

        $groups = [
            'Tickets' => [],
            'Tasks' => [],
            'Users' => [],
            'Organizations' => [],
            'Knowledgebase' => [],
            'Visibility' => [],
            'Admin' => [],
        ];

        foreach ($all as $name) {
            [$prefix] = explode('.', $name, 2);
            $group = match ($prefix) {
                'ticket' => 'Tickets',
                'task' => 'Tasks',
                'user' => 'Users',
                'org' => 'Organizations',
                'kb' => 'Knowledgebase',
                'visibility' => 'Visibility',
                'admin' => 'Admin',
                default => 'Other',
            };
            $groups[$group][] = $name;
        }

        return array_filter($groups, fn ($v) => $v !== []);
    }
}
```

- [ ] **Step 4: Add Role routes inside the admin group**

Open `routes/web.php`. Inside the `admin.` group (replacing the placeholder dashboard route), add:

```php
Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class)
    ->parameters(['roles' => 'role'])
    ->except(['show'])
    ->names('roles');

Route::get('/', fn () => redirect()->route('admin.roles.index'))->name('dashboard');
```

This means `LegacyRole` is route-bound as `{role}` and resolved by ID.

- [ ] **Step 5: Run tests**

Run: `vendor/bin/pest tests/Feature/Admin/RoleAdminTest.php`

Expected: PASS, 8 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin app/Http/Requests/Admin routes/web.php tests/Feature/Admin/RoleAdminTest.php
git commit -m "feat(admin/role): add RoleController + routes + feature tests"
```

---

### Task 14: Inertia `AdminLayout` shell

**Files:**
- Create: `resources/js/components/admin/AdminLayout.tsx`
- Create: `resources/js/components/admin/FormGrid.tsx`
- Create: `resources/js/components/admin/FormSection.tsx`
- Create: `resources/js/components/admin/ConfirmDialog.tsx`

(No automated test — visual / behavioral verification at end of task 16.)

- [ ] **Step 1: Create `AdminLayout.tsx`**

```tsx
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

type NavItem = { href: string; label: string; routeName: string };

const NAV: NavItem[] = [
  { href: '/admin/roles', label: 'Roles', routeName: 'admin.roles.index' },
  // future surfaces appended as they ship
];

export function AdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
  const { url } = usePage();

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b">
        <div className="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between">
          <div className="text-lg font-semibold">osTicket Admin</div>
          <nav className="flex gap-4 text-sm">
            <Link href="/scp" className="text-muted-foreground hover:text-foreground">Back to SCP</Link>
          </nav>
        </div>
      </header>
      <div className="mx-auto max-w-7xl px-4 py-6 grid grid-cols-12 gap-6">
        <aside className="col-span-3">
          <nav className="flex flex-col gap-1">
            {NAV.map((item) => {
              const active = url.startsWith(item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`rounded px-3 py-2 text-sm ${active ? 'bg-accent text-accent-foreground' : 'hover:bg-muted'}`}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>
        </aside>
        <main className="col-span-9">
          {title && <h1 className="text-2xl font-bold mb-4">{title}</h1>}
          {children}
        </main>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Create `FormGrid.tsx` and `FormSection.tsx`**

`FormGrid.tsx`:

```tsx
import { type PropsWithChildren } from 'react';

export function FormGrid({ children }: PropsWithChildren) {
  return <div className="grid grid-cols-12 gap-6">{children}</div>;
}
```

`FormSection.tsx`:

```tsx
import { type PropsWithChildren } from 'react';

export function FormSection({
  title,
  description,
  children,
}: PropsWithChildren<{ title: string; description?: string }>) {
  return (
    <section className="col-span-12 grid grid-cols-12 gap-6 border-b pb-6">
      <header className="col-span-4">
        <h3 className="font-semibold">{title}</h3>
        {description && <p className="text-sm text-muted-foreground mt-1">{description}</p>}
      </header>
      <div className="col-span-8 space-y-4">{children}</div>
    </section>
  );
}
```

- [ ] **Step 3: Create `ConfirmDialog.tsx`**

```tsx
import { type ReactNode } from 'react';

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  onConfirm,
  onClose,
}: {
  open: boolean;
  title: string;
  description: ReactNode;
  confirmLabel: string;
  onConfirm: () => void;
  onClose: () => void;
}) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
      <div className="bg-background rounded-lg shadow-lg p-6 max-w-md w-full">
        <h2 className="text-lg font-semibold mb-2">{title}</h2>
        <div className="text-sm text-muted-foreground mb-6">{description}</div>
        <div className="flex justify-end gap-2">
          <button onClick={onClose} className="px-4 py-2 rounded border text-sm">Cancel</button>
          <button onClick={onConfirm} className="px-4 py-2 rounded bg-destructive text-destructive-foreground text-sm">
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
```

(If the project already has a shadcn `Dialog` primitive, replace the inline modal with that — match existing UI conventions.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/admin
git commit -m "feat(admin): add AdminLayout + FormGrid + FormSection + ConfirmDialog"
```

---

### Task 15: `PermissionMatrix` component

**Files:**
- Create: `resources/js/components/admin/PermissionMatrix.tsx`

- [ ] **Step 1: Implement the component**

```tsx
type Catalog = Record<string, string[]>;

export function PermissionMatrix({
  catalog,
  granted,
  onChange,
}: {
  catalog: Catalog;
  granted: string[];
  onChange: (next: string[]) => void;
}) {
  const grantedSet = new Set(granted);

  function toggle(name: string) {
    const next = new Set(grantedSet);
    if (next.has(name)) next.delete(name);
    else next.add(name);
    onChange(Array.from(next).sort());
  }

  return (
    <div className="space-y-6">
      {Object.entries(catalog).map(([group, items]) => (
        <fieldset key={group} className="border rounded p-4">
          <legend className="px-2 text-sm font-semibold">{group}</legend>
          <div className="grid grid-cols-2 gap-2 mt-2">
            {items.map((name) => (
              <label key={name} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={grantedSet.has(name)}
                  onChange={() => toggle(name)}
                />
                <code className="text-xs">{name}</code>
              </label>
            ))}
          </div>
        </fieldset>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/admin/PermissionMatrix.tsx
git commit -m "feat(admin): add PermissionMatrix component"
```

---

### Task 16: `Roles/Index.tsx`

**Files:**
- Create: `resources/js/Pages/Admin/Roles/Index.tsx`

- [ ] **Step 1: Implement the page**

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';

type Role = { id: number; name: string; permissions_count: number };

export default function RolesIndex({ roles }: { roles: Role[] }) {
  const [confirmDelete, setConfirmDelete] = useState<Role | null>(null);

  return (
    <AdminLayout title="Roles">
      <Head title="Admin · Roles" />
      <div className="mb-4 flex justify-end">
        <Link
          href="/admin/roles/create"
          className="rounded bg-primary text-primary-foreground px-4 py-2 text-sm"
        >
          New role
        </Link>
      </div>
      <table className="w-full text-sm border">
        <thead>
          <tr className="bg-muted/50 text-left">
            <th className="p-3">Name</th>
            <th className="p-3">Permissions</th>
            <th className="p-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          {roles.map((r) => (
            <tr key={r.id} className="border-t">
              <td className="p-3 font-medium">{r.name}</td>
              <td className="p-3 text-muted-foreground">{r.permissions_count}</td>
              <td className="p-3 text-right space-x-2">
                <Link href={`/admin/roles/${r.id}/edit`} className="text-primary text-sm">Edit</Link>
                <button
                  onClick={() => setConfirmDelete(r)}
                  className="text-destructive text-sm"
                >
                  Delete
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <ConfirmDialog
        open={confirmDelete !== null}
        title="Delete role?"
        description={`Are you sure you want to delete "${confirmDelete?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={() => {
          if (confirmDelete) router.delete(`/admin/roles/${confirmDelete.id}`);
          setConfirmDelete(null);
        }}
        onClose={() => setConfirmDelete(null)}
      />
    </AdminLayout>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Roles/Index.tsx
git commit -m "feat(admin/role): add Roles/Index page"
```

---

### Task 17: `Roles/Edit.tsx`

**Files:**
- Create: `resources/js/Pages/Admin/Roles/Edit.tsx`

- [ ] **Step 1: Implement the page**

```tsx
import { Head, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { PermissionMatrix } from '@/components/admin/PermissionMatrix';

type Role = { id: number; name: string };
type Catalog = Record<string, string[]>;

export default function RolesEdit({
  mode,
  role,
  catalog,
  granted,
}: {
  mode: 'create' | 'edit';
  role: Role | null;
  catalog: Catalog;
  granted: string[];
}) {
  const isEdit = mode === 'edit' && role !== null;

  const form = useForm({
    name: role?.name ?? '',
    permissions: granted,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    if (isEdit) {
      form.put(`/admin/roles/${role!.id}`);
    } else {
      form.post('/admin/roles');
    }
  }

  return (
    <AdminLayout title={isEdit ? `Edit role: ${role?.name}` : 'New role'}>
      <Head title={isEdit ? `Edit ${role?.name}` : 'New role'} />
      <form onSubmit={submit} className="space-y-6">
        <FormGrid>
          <FormSection title="Identity" description="Name shown to admins when assigning this role.">
            <div>
              <label className="block text-sm mb-1">Name</label>
              <input
                type="text"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                className="w-full rounded border px-3 py-2 text-sm"
              />
              {form.errors.name && <p className="text-destructive text-xs mt-1">{form.errors.name}</p>}
            </div>
          </FormSection>

          <FormSection title="Permissions" description="Grant fine-grained permissions to staff who have this role.">
            <PermissionMatrix
              catalog={catalog}
              granted={form.data.permissions}
              onChange={(next) => form.setData('permissions', next)}
            />
            {form.errors.permissions && <p className="text-destructive text-xs mt-1">{form.errors.permissions}</p>}
          </FormSection>
        </FormGrid>

        <div className="flex justify-end gap-2">
          <a href="/admin/roles" className="px-4 py-2 rounded border text-sm">Cancel</a>
          <button
            type="submit"
            disabled={form.processing}
            className="px-4 py-2 rounded bg-primary text-primary-foreground text-sm"
          >
            {isEdit ? 'Save' : 'Create'}
          </button>
        </div>
      </form>
    </AdminLayout>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Roles/Edit.tsx
git commit -m "feat(admin/role): add Roles/Edit page"
```

---

### Task 18: Wire navigation, build, and run full test suite

**Files:**
- (Already wired via `AdminLayout` nav array.)

- [ ] **Step 1: Build frontend bundles**

Run: `npm run build`

Expected: Build succeeds, no TypeScript errors. If errors mention missing `@/components/admin/...` paths, verify `tsconfig.json`'s `paths` mapping includes `resources/js/*`.

- [ ] **Step 2: Run the full Phase 2a test surface**

Run: `vendor/bin/pest tests/Unit/Admin tests/Feature/Admin`

Expected: All tests PASS. Total ~25 tests.

- [ ] **Step 3: Run the existing Phase 1 SCP tests to confirm no regression**

Run: `vendor/bin/pest tests/Feature/Scp tests/Feature/Auth`

Expected: All tests PASS.

- [ ] **Step 4: Manual smoke check (dev server)**

Open two terminals.

Terminal 1: `php artisan serve`
Terminal 2: `npm run dev`

In a browser, log in as a staff member with `admin.access`, `admin.role.create`, `admin.role.update`, `admin.role.delete`. Visit `/admin/roles`. Verify:

- [ ] Index page renders the list (or "no roles yet" if empty).
- [ ] "New role" button leads to the create form.
- [ ] Creating a role with a name and a few checked permissions redirects back to the index and the new role appears.
- [ ] Editing a role updates it.
- [ ] Deleting a role (via the confirm dialog) removes it.
- [ ] After each mutation, a row exists in `scp_admin_audit_log` with correct `action` and JSON `before`/`after`. Verify via `php artisan tinker` and `App\Models\Admin\AdminAuditLog::latest()->first()`.

If any UI defect is found, fix inline (this is the stage where the AdminLayout / Edit page polish happens). Commit fixes with `fix(admin/role): ...`.

- [ ] **Step 5: Final commit (only if any UI polish was needed)**

```bash
git add resources/js/components/admin resources/js/Pages/Admin/Roles
git commit -m "fix(admin/role): UI polish from manual smoke test"
```

---

## Self-review notes

- Spec section "Module layout" → covered by Tasks 1–17 (all directories listed are touched).
- Spec section "Routing" → Task 8 (admin entry route) + Task 13 (Role resource routes).
- Spec section "Authorization" → Task 4 (catalog seeder), Task 5 (entry middleware), Task 6 (resolver), Task 7 (gate + trait), Task 10 (policy).
- Spec section "Audit log" → Task 1 (table), Task 2 (model), Task 3 (logger), Task 12 (service-layer integration with diff + sub-table changes).
- Spec section "Migration tool" → Out of scope for this plan (its own future plan).
- Spec testing strategy → Unit tests in Tasks 2/3/6/7/10/12; Feature tests in Tasks 4/5/8/13.

---

## What this plan does NOT cover

- Other 8 admin surfaces (Canned, SLA, Team, Department, Help Topic, Filter, Email Config, Staff). Each gets its own plan after Plan 1 ships.
- Migration tool. Its own plan.
- Browser-level / E2E tests beyond the manual smoke step.
- Spatie Activity Log integration. The spec calls for our own table — Activity Log is installed but unused for this plan.
- Schema-v2 (FK constraints, table renames). Post-Phase-3 cleanup.

---

## Execution

Plan complete. After it ships:

1. Verify all task checkboxes are ticked.
2. Run `vendor/bin/pest` end-to-end one more time.
3. Confirm `git log --oneline` shows ~17 focused commits.
4. Open a PR titled `feat(admin): cross-cutting infra + role surface (Phase 2a #1)`.
5. Begin Plan 2 (Canned Response surface).
