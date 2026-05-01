<?php

declare(strict_types=1);

use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    prepareLegacyMigrationFixture();
    prepareLegacyMigrationTargetSchema();
    seedTargetPermissionCatalog();
});

it('reports per-table counts during dry run', function (): void {
    test()->artisan('legacy:migrate', ['--dry-run' => true])
        ->expectsOutputToContain('role')
        ->expectsOutputToContain('staff')
        ->expectsOutputToContain('Estimated total seconds')
        ->assertSuccessful();
});

it('migrates the small fixture into the target database', function (): void {
    test()->artisan('legacy:migrate')->assertSuccessful();

    expect(DB::connection('osticket2')->table('role')->count())->toBe(2)
        ->and(DB::connection('osticket2')->table('staff')->count())->toBe(2)
        ->and(DB::connection('osticket2')->table('team_member')->count())->toBe(2)
        ->and(DB::connection('osticket2')->table('filter_action')->count())->toBe(1)
        ->and(DB::connection('osticket2')->table('_migration_progress')->where('table_name', 'filter_action')->value('status'))->toBe('completed');

    $migratedStaff = DB::connection('osticket2')->table('staff')->where('staff_id', 1)->first();

    expect($migratedStaff)->not->toBeNull()
        ->and($migratedStaff->created_at)->toBe('2026-01-04 08:00:00')
        ->and($migratedStaff->updated_at)->toBe('2026-01-04 08:00:00');
});

it('verifies migrated data successfully', function (): void {
    test()->artisan('legacy:migrate')->assertSuccessful();

    test()->artisan('legacy:migrate', ['--verify' => true, '--sample' => 10])
        ->expectsOutputToContain('verified')
        ->assertSuccessful();
});

it('resumes from a table using its watermark', function (): void {
    DB::connection('osticket2')->table('_migration_progress')->insert([
        'table_name' => 'role',
        'last_id' => '1',
        'status' => 'running',
        'completed_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->artisan('legacy:migrate', ['--from' => 'role'])->assertSuccessful();

    expect(DB::connection('osticket2')->table('role')->count())->toBe(1)
        ->and(DB::connection('osticket2')->table('role')->value('id'))->toBe(2)
        ->and(DB::connection('osticket2')->table('_migration_progress')->where('table_name', 'role')->value('last_id'))->toBe('2');
});

it('translates legacy role permissions into spatie grants', function (): void {
    test()->artisan('legacy:migrate')->assertSuccessful();

    $rolesTable = config('permission.table_names.roles', 'roles');
    $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
    $permissionTable = config('permission.table_names.permissions', 'permissions');

    $managerRoleId = DB::connection('osticket2')->table($rolesTable)->where('name', 'Managers')->value('id');
    $permissionId = DB::connection('osticket2')->table($permissionTable)->where('name', 'admin.staff.update')->value('id');

    expect($managerRoleId)->toBe(2)
        ->and($permissionId)->not->toBeNull()
        ->and(DB::connection('osticket2')->table($rolePermissionTable)
            ->where('role_id', $managerRoleId)
            ->where('permission_id', $permissionId)
            ->exists())->toBeTrue();
});

function prepareLegacyMigrationFixture(): void
{
    $sql = file_get_contents(base_path('database/migration-fixtures/small/legacy.sql'));

    expect($sql)->not->toBeFalse();

    DB::connection('legacy')->unprepared((string) $sql);
}

function prepareLegacyMigrationTargetSchema(): void
{
    $schema = Schema::connection('osticket2');

    foreach ([
        '_migration_progress',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'roles',
        'permissions',
        'filter_action',
        'filter_rule',
        'filter',
        'canned_response',
        'help_topic',
        'team_member',
        'team',
        'staff_dept_access',
        'staff',
        'department',
        'email_template',
        'email_template_group',
        'email_account',
        'email',
        'sla',
        'role',
    ] as $table) {
        $schema->dropIfExists($table);
    }

    $schema->create('role', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('flags')->default(0);
        $table->string('name', 64);
        $table->text('permissions')->nullable();
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('sla', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('schedule_id')->nullable();
        $table->unsignedInteger('flags')->default(0);
        $table->unsignedInteger('grace_period')->default(0);
        $table->string('name', 64);
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('email', function (Blueprint $table): void {
        $table->unsignedInteger('email_id')->primary();
        $table->unsignedInteger('noautoresp')->default(0);
        $table->unsignedInteger('priority_id')->default(0);
        $table->unsignedInteger('dept_id')->default(0);
        $table->unsignedInteger('topic_id')->default(0);
        $table->string('email', 128);
        $table->string('name', 128);
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('email_account', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('email_id');
        $table->string('type', 32)->nullable();
        $table->string('auth_bk', 255)->nullable();
        $table->string('auth_id', 255)->nullable();
        $table->unsignedInteger('active')->default(0);
        $table->string('host', 128)->nullable();
        $table->unsignedInteger('port')->nullable();
        $table->string('folder', 64)->nullable();
        $table->string('protocol', 32)->nullable();
        $table->string('encryption', 32)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('email_template_group', function (Blueprint $table): void {
        $table->unsignedInteger('tpl_id')->primary();
        $table->unsignedInteger('isactive')->default(1);
        $table->string('name', 128);
        $table->string('lang', 16)->nullable();
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('email_template', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('tpl_id')->nullable();
        $table->string('code_name', 128)->nullable();
        $table->string('subject', 255)->nullable();
        $table->text('body')->nullable();
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('department', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('dept_id')->nullable();
        $table->unsignedInteger('tpl_id')->nullable();
        $table->unsignedInteger('sla_id')->nullable();
        $table->unsignedInteger('manager_id')->nullable();
        $table->unsignedInteger('email_id')->nullable();
        $table->string('name', 128);
        $table->text('signature')->nullable();
        $table->unsignedInteger('ispublic')->default(0);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('staff', function (Blueprint $table): void {
        $table->unsignedInteger('staff_id')->primary();
        $table->unsignedInteger('dept_id');
        $table->unsignedInteger('role_id')->nullable();
        $table->string('username', 64);
        $table->string('firstname', 64)->nullable();
        $table->string('lastname', 64)->nullable();
        $table->string('email', 128)->nullable();
        $table->string('phone', 64)->nullable();
        $table->string('mobile', 64)->nullable();
        $table->text('signature')->nullable();
        $table->string('passwd', 255)->nullable();
        $table->unsignedInteger('isactive')->default(1);
        $table->unsignedInteger('isadmin')->default(0);
        $table->unsignedInteger('isvisible')->default(1);
        $table->unsignedInteger('change_passwd')->default(0);
        $table->timestamp('passwdreset')->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('lastlogin')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('staff_dept_access', function (Blueprint $table): void {
        $table->unsignedInteger('staff_id');
        $table->unsignedInteger('dept_id');
        $table->unsignedInteger('role_id')->default(0);
        $table->unsignedInteger('flags')->default(0);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->primary(['staff_id', 'dept_id']);
    });

    $schema->create('team', function (Blueprint $table): void {
        $table->unsignedInteger('team_id')->primary();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('flags')->default(0);
        $table->string('name', 128);
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('team_member', function (Blueprint $table): void {
        $table->unsignedInteger('team_id');
        $table->unsignedInteger('staff_id');
        $table->unsignedInteger('flags')->default(0);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->primary(['team_id', 'staff_id']);
    });

    $schema->create('help_topic', function (Blueprint $table): void {
        $table->unsignedInteger('topic_id')->primary();
        $table->unsignedInteger('topic_pid')->default(0);
        $table->unsignedInteger('ispublic')->default(1);
        $table->unsignedInteger('noautoresp')->default(0);
        $table->unsignedInteger('flags')->default(0);
        $table->unsignedInteger('status_id')->default(0);
        $table->unsignedInteger('priority_id')->default(0);
        $table->unsignedInteger('dept_id')->nullable();
        $table->unsignedInteger('staff_id')->nullable();
        $table->unsignedInteger('team_id')->nullable();
        $table->unsignedInteger('sla_id')->nullable();
        $table->unsignedInteger('form_id')->nullable();
        $table->unsignedInteger('isactive')->default(1);
        $table->unsignedInteger('disabled')->default(0);
        $table->unsignedInteger('page_id')->default(0);
        $table->unsignedInteger('sequence_id')->default(0);
        $table->unsignedInteger('sort')->default(0);
        $table->string('topic', 128);
        $table->string('number_format', 128)->nullable();
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('canned_response', function (Blueprint $table): void {
        $table->unsignedInteger('canned_id')->primary();
        $table->unsignedInteger('dept_id')->nullable();
        $table->unsignedInteger('isenabled')->default(1);
        $table->string('title', 128);
        $table->text('response')->nullable();
        $table->string('lang', 16)->nullable();
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('filter', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('execorder')->default(0);
        $table->unsignedInteger('isactive')->default(1);
        $table->unsignedInteger('flags')->default(0);
        $table->unsignedInteger('status')->default(0);
        $table->unsignedInteger('match_all_rules')->default(0);
        $table->unsignedInteger('stop_onmatch')->default(0);
        $table->string('target', 64)->nullable();
        $table->unsignedInteger('email_id')->nullable();
        $table->string('name', 128);
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('filter_rule', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('filter_id');
        $table->string('what', 64)->nullable();
        $table->string('how', 64)->nullable();
        $table->string('val', 255)->nullable();
        $table->unsignedInteger('isactive')->default(1);
        $table->string('notes', 255)->nullable();
        $table->timestamp('created')->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('filter_action', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('filter_id');
        $table->unsignedInteger('sort')->default(0);
        $table->string('type', 64)->nullable();
        $table->string('target', 255)->nullable();
        $table->timestamp('updated')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    $schema->create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name'], 'scp_permissions_name_guard_unique');
    });

    $schema->create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name'], 'scp_roles_name_guard_unique');
    });

    $schema->create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    $schema->create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    $schema->create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    $schema->create('_migration_progress', function (Blueprint $table): void {
        $table->string('table_name', 120)->primary();
        $table->string('last_id', 255)->nullable();
        $table->string('status', 32)->default('pending');
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
}

function seedTargetPermissionCatalog(): void
{
    $registrar = app(PermissionRegistrar::class);
    $originalConnection = config('permission.connection');

    $registrar->forgetCachedPermissions();
    config()->set('permission.connection', 'osticket2');

    try {
        test()->seed(PermissionCatalogSeeder::class);
    } finally {
        config()->set('permission.connection', $originalConnection);
        $registrar->forgetCachedPermissions();
    }
}
