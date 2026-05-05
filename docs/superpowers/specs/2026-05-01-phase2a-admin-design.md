# Phase 2a — Admin CRUD — Design

**Status:** Draft — pending user review
**Date:** 2026-05-01
**Supersedes (in scope):** the Phase 2a sketch in `.context/plan.md` (post-auth roadmap)

## Context

osTicket 2.0 is a Laravel rewrite of legacy osTicket. Auth and Phase 1 (the read-only Staff Control Panel — "Mirror") are shipped. Phase 1 runs **locally only**; nothing is deployed to a production environment with real customer data yet. The rewrite's Spatie permission tables, `osticket2`-prefixed connection, audit log middleware, and outbound mail guard already exist; the new admin work layers on top.

Phase 2a extends the new app with full administrative CRUD across nine legacy admin surfaces and ships the migration tool that lets a customer move from a legacy install to the new app's own database.

## Goals

1. **Full parity** with legacy admin across 9 surfaces — Role, Canned Response, SLA, Team, Department, Help Topic, Filter, Email Config, Staff. Admins stop using legacy admin entirely after Phase 2a ships.
2. **Migration tool** (`php artisan legacy:migrate`) that copies legacy install data into the new app's own DB, preserving schema and adding Eloquent-friendly timestamps. The new app owns its DB exclusively post-migration.
3. **Single permission system** (Spatie) governing both new admin surfaces and inherited legacy permission semantics; no runtime read of legacy `role.permissions` JSON.
4. **Auditability** of every admin mutation via a new `scp_admin_audit_log` table with before/after diffs.

## Locked decisions

| # | Decision | Notes |
|---|---|---|
| 1 | **Phase 2a done = full parity, all 9 surfaces, ~10 wk** | Initial commit was 8–10 wk; detailed sub-project breakdown lands at ~10 wk realistic (9.5 wk surfaces + 0.5–1 wk shared infra; migration tool runs in parallel). Within tolerance of the original commitment. |
| 2 | **Migrate-once cutover** | Customer takes a maintenance window; legacy DB becomes archive, new app is sole writer. No permanent dual-run on shared DB. |
| 3 | **Pragmatic schema (path iii)** | Migration preserves legacy schema. Additive `created_at`/`updated_at` columns alongside legacy `created`/`updated`. **No FK constraints in Phase 2a.** Schema-v2 with renaming + FKs deferred to post-Phase-3. |
| 4 | **Migration tool is part of Phase 2a** | Lands inside the same phase; gates production deployment. |
| 5 | **Pure Spatie permissions** | Catalog seeded in the new app. Migration tool translates legacy `role.permissions` JSON to Spatie grants once. Spatie is authoritative thereafter. |
| 6 | **Risk-first surface ordering** | Role → Canned Response → SLA → Team → Department → Help Topic → Filter → Email Config → Staff. Front-loads pattern-establishing work; Staff last because of FK fanout + auth/2FA coupling. |
| 7 | **Per-dept role override via custom resolver** | `DepartmentRoleResolver::roleForDepartment()` + a custom Gate. Spatie's native model preserved; per-dept context layered on top. |
| 8 | **Last-write-wins under multi-admin in our app** | Matches legacy behavior. Future optimistic concurrency is a Phase 2a.5+ option, not in scope. |
| 9 | **Audit log writes from the service layer** | Not from middleware (too coarse) and not from model observers (too fine). Each surface's service explicitly calls `AuditLogger::record()`. |
| 10 | **Permission key naming preserved exactly from legacy** | `ticket.create` not `tickets.create`. New admin permissions use `admin.{surface}.{action}` (strictly enumerated, no wildcard grants). |

## Out of scope

- Internal ticket actions (notes, assign, on-hold, drafts, locks). Phase 2b.
- Any customer-facing ticket write (reply, close, create, attachments, dynamic-form writes). Phase 3.
- Schema rename / FK addition / Laravel-idiomatic schema redesign. Post-Phase-3 cleanup.
- Optimistic concurrency / record locking on admin entities.
- Inertia/React browser-level tests for admin flows beyond what Phase 1 patterns cover.
- Per-customer migration ETL (data transformation beyond timestamp upgrade). The tool is straight data copy.
- Plugin compatibility for legacy plugins that wrote to `ost_*` tables. Day-1 break under migration-first; revisited (or accepted as broken) in Phase 3.
- Forever-retention pruning of `scp_admin_audit_log`. Add a `pruneBefore()` command in Phase 3.
- Rebuild of the `_search` FULLTEXT table on migration day. Search recall builds incrementally as new content lands; a separate `php artisan scp:search:rebuild` command is offered as a follow-up.
- Renaming the `legacy` connection to `osticket2`. Both names persist post-migration pointing at the same DB.

## Architecture

### Module layout

Mirror the existing `Scp/` conventions.

```
app/
  Http/Controllers/Admin/
    RoleController.php
    StaffController.php
    DepartmentController.php
    TeamController.php
    SlaController.php
    CannedResponseController.php
    HelpTopicController.php
    FilterController.php
    EmailConfigController.php
  Http/Middleware/
    EnsureAdminAccess.php          ← composes auth:staff + Spatie 'admin.access'
  Http/Requests/Admin/
    Role/{Store,Update}RoleRequest.php
    Staff/{Store,Update}StaffRequest.php
    ... per surface
  Services/Admin/
    PermissionCatalog.php          ← canonical permission key list
    DepartmentRoleResolver.php     ← per-dept role resolver
    AuditLogger.php                ← writes admin_audit_log entries
    RoleService.php
    StaffService.php
    DepartmentService.php
    TeamService.php
    SlaService.php
    CannedResponseService.php
    HelpTopicService.php
    FilterService.php
    EmailConfigService.php
  Models/Admin/
    AdminAuditLog.php
  Migration/
    LegacyMigrator.php             ← orchestrator
    Migrators/
      AbstractMigrator.php
      RoleMigrator.php
      StaffMigrator.php
      DepartmentMigrator.php
      TeamMigrator.php
      SlaMigrator.php
      HelpTopicMigrator.php
      CannedResponseMigrator.php
      FilterMigrator.php
      EmailConfigMigrator.php
      TicketMigrator.php
      ThreadMigrator.php
      AttachmentMigrator.php
      ... (one per legacy table or table-cluster)
    PermissionsTranslator.php      ← legacy role.permissions JSON → Spatie grants
    Verifiers/
      RowCountVerifier.php
      SampleDiffVerifier.php
  Console/Commands/
    LegacyMigrateCommand.php       ← `php artisan legacy:migrate`

resources/js/Pages/Admin/
  Roles/{Index,Edit}.tsx
  Staff/{Index,Edit}.tsx
  Departments/{Index,Edit}.tsx
  Teams/{Index,Edit}.tsx
  Slas/{Index,Edit}.tsx
  CannedResponses/{Index,Edit}.tsx
  HelpTopics/{Index,Edit}.tsx
  Filters/{Index,Edit}.tsx
  EmailConfig/{Index,Edit}.tsx

resources/js/components/admin/
  AdminLayout.tsx
  FormGrid.tsx
  FormSection.tsx
  PermissionMatrix.tsx
  ConfirmDialog.tsx

database/migrations/
  YYYY_MM_DD_create_admin_audit_log_table.php

database/seeders/
  PermissionCatalogSeeder.php

database/migration-fixtures/
  small/ medium/ realistic/        ← synthetic legacy SQL dumps for migration tests
```

### Routing

```php
Route::middleware(['auth:staff', 'admin.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles',           RoleController::class)->except(['show']);
    Route::resource('staff',           StaffController::class)->except(['show']);
    Route::resource('departments',     DepartmentController::class)->except(['show']);
    Route::resource('teams',           TeamController::class)->except(['show']);
    Route::resource('slas',            SlaController::class)->except(['show']);
    Route::resource('canned-responses', CannedResponseController::class)->except(['show']);
    Route::resource('help-topics',     HelpTopicController::class)->except(['show']);
    Route::resource('filters',         FilterController::class)->except(['show']);
    Route::resource('email-config',    EmailConfigController::class)->except(['show']);
});
```

Per-action gates layered on top via `can:admin.{surface}.{action}` middleware (or `$this->authorize(...)` in the controller, backed by per-surface Policy classes).

### Authorization

**Single source of truth: Spatie.** No legacy `role.permissions` JSON read at runtime.

**Permission catalog** (`database/seeders/PermissionCatalogSeeder.php`), grouped by category, ~30–50 keys:

```
Tickets:        ticket.create, ticket.edit, ticket.assign, ticket.transfer,
                ticket.reply, ticket.close, ticket.delete, ticket.release,
                ticket.markanswered
Tasks:          task.create, task.edit, task.assign, task.transfer,
                task.reply, task.close, task.delete
Users:          user.create, user.edit, user.delete, user.manage, user.dir
Organizations:  org.create, org.edit, org.delete
Knowledgebase:  kb.premade, kb.faq
Visibility:     visibility.agents, visibility.departments, visibility.private
Admin (new):    admin.access, and per-surface enumerated:
                admin.role.{create,update,delete}, admin.staff.{create,update,delete},
                admin.department.{create,update,delete}, admin.team.{create,update,delete},
                admin.sla.{create,update,delete}, admin.canned.{create,update,delete},
                admin.helptopic.{create,update,delete}, admin.filter.{create,update,delete},
                admin.email.{create,update,delete}
```

The first six groups mirror what `RolePermission::register()` exposes in legacy. The seventh is new — Phase 2a admin-UI gates that didn't exist in legacy. The seeded catalog is verified against legacy class constants via a one-shot offline check (a script that parses legacy PHP and compares to the seeder).

**Per-dept role override.** Spatie does not natively support "user has Role X by default but Role Y when acting in Department Z." A thin custom layer:

```php
class DepartmentRoleResolver
{
    public function roleForDepartment(Staff $staff, int $deptId): ?Role
    {
        // 1. Look up StaffDeptAccess for ($staff, $deptId)
        // 2. If found, return its role
        // 3. Otherwise, fall back to the staff's primary role
    }
}

Gate::define('can-in-department', function (Staff $staff, string $perm, int $deptId) {
    $role = app(DepartmentRoleResolver::class)->roleForDepartment($staff, $deptId);
    return $role?->hasPermissionTo($perm) ?? false;
});
```

A trait on `Staff` provides ergonomic helpers:

```php
$staff->canForTicket('ticket.assign', $ticket);     // resolves dept from $ticket
$staff->canInDept('ticket.reply', $deptId);
```

For dept-agnostic admin actions (`admin.role.create` etc.), the standard Spatie `$user->can('admin.role.create')` is sufficient — no resolver needed.

**Two-level gating per request:**

1. **Entry gate** — `EnsureAdminAccess` middleware checks `admin.access`. Fail → 403.
2. **Per-action gate** — `can:admin.role.create` middleware on the route, *or* `$this->authorize('create', Role::class)` in the controller. Each surface gets a Policy class (`RolePolicy`, `StaffPolicy`, ...). Authorization logic lives in policies, not controllers.

**Concurrency.** Last-write-wins. No optimistic concurrency in Phase 2a. The `created_at`/`updated_at` columns added by migration are a future-proof — a Phase 2a.5 could add `WHERE updated_at = ?` checks without schema change.

### Audit log

**Table:** `scp_admin_audit_log` (connection `osticket2`).

```
id             BIGINT UNSIGNED PK AUTO_INCREMENT
actor_id       BIGINT UNSIGNED NOT NULL    -- staff who performed the action
action         VARCHAR(64)     NOT NULL    -- 'role.update', 'staff.delete', etc.
subject_type   VARCHAR(64)     NOT NULL    -- 'Role', 'Staff', etc.
subject_id     BIGINT UNSIGNED NOT NULL
before         JSON            NULL        -- null on create
after          JSON            NULL        -- null on delete
metadata       JSON            NULL        -- request id, additional context
ip_address     VARCHAR(45)     NULL
user_agent     VARCHAR(255)    NULL
created_at     TIMESTAMP       NOT NULL

INDEX (subject_type, subject_id, created_at DESC)
INDEX (actor_id, created_at DESC)
INDEX (action, created_at DESC)
```

**Writer.** Single `Services/Admin/AuditLogger.php` with one public method:

```php
public function record(
    Staff $actor,
    string $action,
    Model $subject,
    ?array $before = null,
    ?array $after = null,
    ?array $metadata = null,
): AdminAuditLog;
```

Called explicitly from each surface's service after a successful mutation. Pulls IP/UA/request-id from a service-injected request accessor.

**Diff strategy.**

- **Create:** `before = null`, `after = $model->toArray()` minus excluded fields.
- **Update:** projection of `getDirty()` keys: `before = $model->getOriginal(...)`, `after = $model->fresh()->only(...)`. Only changed fields recorded.
- **Delete:** `before = $model->toArray()` minus excluded, `after = null`.

**Field exclusion list.** Per-model static array:

```php
class Staff extends Model
{
    protected static $auditExcluded = ['password', 'remember_token', '2fa_secret', 'api_key'];
}
```

The audit logger reads this and replaces matching keys with `'[redacted]'` before serializing. Logging a password change emits `{"password": "[redacted]"}` for both `before` and `after` — captures that the change happened, but not the secret.

**Sub-table changes.** When editing a Role, permission grants live in Spatie's `role_has_permissions` pivot. The diff includes a synthetic `permissions` field:

```json
{
  "before": { "name": "Manager", "permissions": ["ticket.assign", "ticket.transfer"] },
  "after":  { "name": "Manager", "permissions": ["ticket.assign", "ticket.transfer", "ticket.reply"] }
}
```

Same pattern for Staff dept access (`StaffDeptAccess` rows): `dept_access: { added: [...], removed: [...], changed: [...] }`. The service computes these synthetic fields before save.

**Retention.** No retention policy in Phase 2a. Audit log grows forever. `AdminAuditLog::pruneBefore($date)` cleanup command lands in Phase 3 once we have data to estimate growth.

**No retroactive audit.** The log starts when a customer begins using the new admin UI. Pre-migration changes done in legacy admin are not represented (legacy didn't audit them either).

### Migration tool

**Command.** `php artisan legacy:migrate [--dry-run] [--verify] [--from=<table>]`.

**Connections.** `legacy` (source: legacy install's DB) → `osticket2` (target: new app's DB). Post-migration both connection names persist in `config/database.php` pointed at the new DB; `legacy` is a historical alias used by existing Phase 1 read code. Renaming is a Phase 3+ cleanup.

**Order of operations.**

1. **Pre-flight.** Source reachable, target reachable, `php artisan migrate` already run on target, `PermissionCatalogSeeder` already seeded.
2. **Per-table copy** in dependency order:
   - `Role` (copies legacy `role` rows + creates corresponding Spatie role records, preserving legacy `role.id` as the Spatie role's foreign-key-stable identifier).
   - **`PermissionsTranslator`** runs immediately after `RoleMigrator` — for each migrated role, read legacy `permissions` JSON and grant matching Spatie permissions. Must complete before Staff migration so `$staff->assignRole()` calls have populated permission grants behind them.
   - `SLA → EmailConfig → Department → Staff → StaffDeptAccess → Team → TeamMember → HelpTopic → CannedResponse → Filter → FilterRule → FilterAction → Ticket → Thread → ThreadEntry → ThreadEvent → Attachment → __cdata → form_entry_values → ...`
3. **Verification.** Per-table row count + sample diff (default 100 rows, full column compare).
4. **Report.** Per-table outcome, total time, anomalies.

**Idempotence.** Watermark table `_migration_progress (table_name, last_id, status, completed_at)`. Each migrator records progress; re-running picks up from the watermark. Required because multi-hour migrations on real installs hit network blips, OOM, etc.

**Additive timestamp upgrade.**

- During copy, each row gets `created_at` and `updated_at`:
  - If legacy `created` exists and is non-null → `created_at = created`.
  - Otherwise → `created_at = now()`.
  - Same for `updated_at` from `updated`.
- Both legacy and new columns coexist. Phase 1 read code continues using `created`; new code uses `created_at`.
- No FK constraints added. No column renames. No table renames.

**Permissions translation.**

```php
foreach (LegacyRole::all() as $legacyRole) {
    $spatieRole = SpatieRole::firstOrCreate(['name' => $legacyRole->name, 'guard_name' => 'staff']);
    $perms = json_decode($legacyRole->permissions, true) ?: [];
    foreach ($perms as $key => $value) {
        if ($value && Permission::where('name', $key)->where('guard_name', 'staff')->exists()) {
            $spatieRole->givePermissionTo($key);
        }
    }
}
```

The legacy permissions JSON is a flat key-value: `{"ticket.create": true, "ticket.edit": false, ...}`. `true` means granted, `false` or missing means not granted.

**Verification.**

- **Row count:** `count(*) source == count(*) target`. Hard fail on mismatch.
- **Sample diff:** N random rows per table (configurable via `--sample=N`, default 100), full column-by-column compare, hard fail on diff.
- `php artisan legacy:migrate --verify` runs verification only — useful after a manual data fix mid-migration.

**Dry-run and estimate.** `php artisan legacy:migrate --dry-run` reports per-table row counts and a rough time estimate (seconds per million rows benchmarked on a synthetic seed). Customer plans their maintenance window from this output.

**In scope.** All `ost_*` tables that have a corresponding Eloquent model in `app/Models/`. Includes pivot/sub-tables (`team_member`, `staff_dept_access`, `filter_rule`, `filter_action`, `email_template`), tickets + threading + attachments + `__cdata` + dynamic-form values.

**Out of scope.** `_search` FULLTEXT table (rebuilt incrementally; separate `scp:search:rebuild` command for Day-1 recall). Optional/advisory tables (`syslog`, plugin-private tables) — listed individually with `--skip-table=<name>` flags.

**Maintenance window.** Tool is offline-only. Customer takes legacy down (or read-only), runs `legacy:migrate`, brings the new app up. No live shadow / cut-over.

**Failure modes.**

- Source unreachable → fail fast, no partial migration.
- Per-table failure → mark watermark as failed, exit non-zero.
- Verification mismatch → log diff details, exit non-zero. Operator decides whether to re-run or investigate.

## Deliverable order (sub-projects)

| # | Sub-project | Estimate | Notes |
|---|---|---|---|
| 0 | **Cross-cutting infra** | 0.5–1 wk | `admin.` route group, `EnsureAdminAccess` middleware, `PermissionCatalogSeeder`, `AuditLogger` + `scp_admin_audit_log` migration, `DepartmentRoleResolver`, base `AdminFormRequest`, Inertia `AdminLayout`/`FormGrid`/`FormSection`/`PermissionMatrix`/`ConfirmDialog` components, base Pest Feature test patterns. **All landed before Surface #1.** |
| 1 | **Role** | 1.5 wk | Most pattern-establishing surface: permission catalog editing, role assignment to staff (via Spatie), audit log diff with sub-table (permissions) changes. Validates Spatie write-side end-to-end. |
| 2 | **Canned Response** | 0.5 wk | Quick validation of patterns: single table + optional Department FK + rich-text body. |
| 3 | **SLA** | 0.5 wk | Single table, simple validation. Validates "config that lots of things depend on." |
| 4 | **Team** | 0.5 wk | Composite-PK members table (`team_member`). Lead is an optional Staff FK. |
| 5 | **Department** | 1 wk | Multi-FK (Role default, SLA, Email Config, Staff manager). Stresses FK validation, dropdown population, dependency error UX. |
| 6 | **Help Topic** | 1 wk | References SLA + Department + form mapping (read-only on form definitions). |
| 7 | **Filter** | 1.5 wk | Nested rules + actions sub-tables. Rich form UX with add/remove rows. |
| 8 | **Email Config** | 1.5 wk | Three sub-surfaces (mail accounts, templates, template groups). Mail account credentials = sensitive (encryption at rest). Template editing surface. |
| 9 | **Staff** | 1.5 wk | FK heaviest. Password set/reset, role assignment, dept access (`StaffDeptAccess`), team membership, 2FA fields. Last because it benefits most from all earlier patterns. |
| M | **Migration tool** | 1.5 wk | Parallel track, can develop alongside surfaces #1–#5. Lands by end of phase. |

**Estimated total: 9.5–10.5 wk** of focused work. Migration tool in parallel keeps the schedule from extending sequentially.

## Testing strategy

**1. Unit tests** (Pest, no DB):

- Per-surface service methods — validation, FK lookup, side-effect ordering.
- `AuditLogger::record()` — diff logic, field exclusion, sub-table change capture.
- `DepartmentRoleResolver::roleForDepartment()` — primary role fallback, override resolution, edge cases (deleted role on a `staff_dept_access` row, staff with no dept access).
- `PermissionsTranslator` — every legacy permissions JSON shape we observe in synthetic fixtures translates to the right Spatie grant set.

**2. Feature tests** (Pest, full HTTP, against per-test-seeded `osticket2` connection):

- One file per surface: `tests/Feature/Admin/{Role,Staff,Department,Team,Sla,CannedResponse,HelpTopic,Filter,EmailConfig}AdminTest.php`.
- Each covers: index, create (happy + invalid), update (happy + invalid + unauthorized), delete (happy + blocked-by-FK), per-action authorization (a staff missing `admin.role.create` gets 403).
- **Audit-log assertions per mutation:** every successful mutation writes an `AdminAuditLog` row with correct `actor_id`, `action`, `subject_type/id`, `before`, `after`. Failed mutations write nothing.
- **Cross-surface authorization:** `tests/Feature/Admin/PerDeptRoleResolverTest.php` — staff with primary role `Agent` and `Manager` override on Dept 5 can perform Manager-level actions only when the dept context is 5.

**3. Migration tool tests** (Pest, integration, against synthetic fixtures):

- `database/migration-fixtures/{small,medium,realistic}/` — three SQL dumps of synthetic legacy data. Hand-curated to exercise edge cases: orphaned references, null timestamps, malformed JSON in `role.permissions`, unicode names, etc.
- One test per migrator class: source fixture → run migrator → assert target rows match expected (full column compare).
- One end-to-end test per fixture: full `legacy:migrate` orchestration, verification step passes.
- **Regression test:** after migration, every Phase 1 read service still returns the same data it did pre-migration. Exercises the "Phase 1 code unchanged" claim.

**Out of testing scope (Phase 2a):**

- Inertia/React component tests beyond what Phase 1 patterns cover. Browser tests for admin flows are deferred. Feature tests above cover the API surface and Inertia responses.
- Permission catalog completeness vs legacy. Covered by a one-shot offline check (parse legacy class constants for `RolePermission::register()` calls, compare to seeder output). Not run in CI.

**Coverage target.** No numeric target. Every service method has at least one happy-path + one error-path test; every controller action has at least one authorized + one unauthorized feature test.

## Open considerations / follow-ups

- **Browser / E2E tests for admin flows.** Deferred. Likely sub-project alongside Phase 2b.
- **Permission catalog drift.** If legacy adds a permission key after our snapshot, our seeder is stale. Documented as a periodic offline check, not CI-enforced.
- **`_search` rebuild on Day 1.** Separate command (`scp:search:rebuild`) — recommended but not in Phase 2a scope.
- **Pre-migration data quality survey.** Real installs will have orphaned rows, malformed JSON, etc. The migration tool's verification phase catches these but doesn't pre-empt them. A `php artisan legacy:check` companion command would surface issues without doing a real migration. Phase 2a.5 candidate.
- **Migration window estimation.** Synthetic-seed-derived per-row time estimates in `--dry-run` may be inaccurate on real production hardware. Document the estimate as advisory; encourage customers to test on a snapshot first.
- **Connection rename (`legacy` → `osticket2`).** Post-Phase-3 cleanup. Touches ~80 read-only models.
- **FK constraints.** Deferred to schema-v2 (post-Phase-3). Adding them requires per-customer data quality work.
- **Optimistic concurrency.** Future Phase 2a.5 add-on. The `created_at`/`updated_at` columns from migration enable it without schema change.
- **Audit log retention.** `AdminAuditLog::pruneBefore()` lands in Phase 3.
- **Plugin compatibility.** Legacy plugins that wrote to `ost_*` tables don't fire under migration-first. Day-1 break, accepted. Phase 3 may revisit.

## References

- `.context/plan.md` — Post-auth roadmap, original Phase 1 spec, Phase 2/3 sketches
- `docs/dynamic-forms-strategy.md` — Phase 1 dynamic-forms approach
- `docs/models.md` — Eloquent model documentation
