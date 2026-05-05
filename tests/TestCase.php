<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->isRunningPhpUnit() && config('database.connections.legacy.driver') !== 'sqlite') {
            throw new \RuntimeException(
                'Refusing to run tests against a non-sqlite legacy database. '.
                'Set LEGACY_DB_DRIVER=sqlite and LEGACY_DB_DATABASE to a disposable test database.'
            );
        }

        // Prevent 60-second SQLite busy waits when multiple connections
        // (sqlite, legacy, osticket2) share the same database file in tests.
        if (config('database.connections.legacy.driver') === 'sqlite') {
            DB::connection('sqlite')->statement('PRAGMA busy_timeout = 0');
            DB::connection('legacy')->statement('PRAGMA busy_timeout = 0');
            DB::connection('osticket2')->statement('PRAGMA busy_timeout = 0');
        }

        if (config('database.connections.legacy.driver') === 'sqlite') {
            $dbPath = config('database.connections.legacy.database');

            if ($dbPath && $dbPath !== ':memory:') {
                touch($dbPath);
            }

            $legacy = Schema::connection('legacy');
            $legacyConnection = DB::connection('legacy');
            $osticket2 = Schema::connection('osticket2');
            $osticket2Connection = DB::connection('osticket2');

            if ($this->legacyStaffTableNeedsRebuild()) {
                $legacy->dropIfExists('staff');
            }

            // Clean up tables left behind by older test runs before the
            // prefix changed from osticket2_ to scp_.
            foreach ([
                'osticket2_staff_two_factor',
                'osticket2_staff_auth_migrations',
            ] as $staleTable) {
                $legacyConnection->statement("drop table if exists {$staleTable}");
            }

            $this->ensureLegacyTable($legacy, 'staff', function (Blueprint $table) {
                $table->unsignedInteger('staff_id')->autoIncrement();
                $table->unsignedInteger('dept_id')->default(0);
                $table->string('username', 32)->unique();
                $table->string('firstname', 64)->default('');
                $table->string('lastname', 64)->default('');
                $table->string('email', 128)->default('');
                $table->string('passwd', 128)->default('');
                $table->tinyInteger('isactive')->default(1);
                $table->tinyInteger('isadmin')->default(0);
                $table->timestamp('created')->useCurrent();
                $table->timestamp('lastlogin')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'staff_dept_access', function (Blueprint $table) {
                $table->unsignedInteger('staff_id');
                $table->unsignedInteger('dept_id');
                $table->unsignedInteger('role_id')->default(0);
                $table->unsignedInteger('flags')->default(0);
                $table->primary(['staff_id', 'dept_id']);
            });

            $this->ensureLegacyTable($legacy, 'session', function (Blueprint $table) {
                $table->string('session_id', 64)->primary();
                $table->unsignedInteger('user_id')->default(0);
                $table->text('session_data')->nullable();
                $table->dateTime('session_expire')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });

            $this->ensureLegacyTable($legacy, 'roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });

            $this->ensureLegacyTable($legacy, 'model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });

            $this->ensureLegacyTable($legacy, 'model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            });

            $this->ensureLegacyTable($legacy, 'role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });

            $this->ensureLegacyTable($legacy, 'ticket', function (Blueprint $table) {
                $table->unsignedInteger('ticket_id')->autoIncrement();
                $table->string('number', 20)->unique();
                $table->unsignedInteger('user_id')->default(0);
                $table->unsignedInteger('status_id')->default(1);
                $table->unsignedInteger('dept_id')->default(0);
                $table->unsignedInteger('staff_id')->default(0);
                $table->unsignedInteger('team_id')->default(0);
                $table->unsignedInteger('sla_id')->default(0);
                $table->unsignedInteger('email_id')->default(0);
                $table->string('source', 32)->default('web');
                $table->string('ip_address', 45)->nullable();
                $table->tinyInteger('isoverdue')->default(0);
                $table->tinyInteger('isanswered')->default(0);
                $table->dateTime('duedate')->nullable();
                $table->dateTime('closed')->nullable();
                $table->dateTime('lastupdate')->useCurrent();
                $table->dateTime('lastmessage')->useCurrent();
                $table->dateTime('lastresponse')->useCurrent();
                $table->dateTime('created')->useCurrent();
                $table->dateTime('updated')->useCurrent();
            });

            $this->ensureLegacyTable($legacy, 'lock', function (Blueprint $table) {
                $table->unsignedInteger('lock_id')->autoIncrement();
                $table->char('object_type', 1)->default('T');
                $table->unsignedInteger('object_id');
                $table->unsignedInteger('staff_id');
                $table->dateTime('expire');
                $table->unique(['object_type', 'object_id']);
            });

            $this->ensureLegacyTable($legacy, 'config', function (Blueprint $table) {
                $table->id();
                $table->string('namespace', 64)->default('core');
                $table->string('key', 64);
                $table->text('value')->nullable();
                $table->timestamp('updated')->nullable();
                $table->unique(['namespace', 'key']);
            });

            $this->ensureLegacyTable($legacy, 'thread', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('object_id');
                $table->char('object_type', 1)->default('T');
                $table->dateTime('created')->useCurrent();
                $table->dateTime('lastresponse')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'queue', function (Blueprint $table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('parent_id')->default(0);
                $table->unsignedInteger('staff_id')->default(0);
                $table->string('flags', 255)->default('');
                $table->string('title', 255);
                $table->text('config')->nullable();
                $table->dateTime('created')->useCurrent();
                $table->dateTime('updated')->useCurrent();
            });

            $this->ensureLegacyTable($legacy, 'draft', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->string('namespace', 255);
                $table->longText('body');
                $table->dateTime('created')->useCurrent();
                $table->dateTime('updated')->useCurrent();
            });

            $this->ensureLegacyTable($legacy, 'event', function (Blueprint $table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->string('name', 64);
                $table->text('description')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'ticket_status', function (Blueprint $table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->string('name', 64);
                $table->string('state', 32);
                $table->string('mode')->nullable();
                $table->string('flags')->nullable();
                $table->integer('sort')->default(0);
                $table->json('properties')->nullable();
                $table->timestamp('created')->nullable();
                $table->timestamp('updated')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'thread_entry', function (Blueprint $table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('thread_id');
                $table->unsignedInteger('staff_id')->default(0);
                $table->unsignedInteger('user_id')->default(0);
                $table->char('type', 1);
                $table->string('poster')->default('');
                $table->string('source')->default('');
                $table->string('title')->default('');
                $table->text('body')->nullable();
                $table->string('format')->default('text');
                $table->dateTime('created')->nullable();
                $table->dateTime('updated')->nullable();
            });

            $this->ensureLegacyTable($legacy, 'thread_event', function (Blueprint $table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('thread_id');
                $table->char('thread_type', 1);
                $table->unsignedInteger('event_id');
                $table->unsignedInteger('staff_id');
                $table->unsignedInteger('team_id')->default(0);
                $table->unsignedInteger('dept_id')->default(0);
                $table->unsignedInteger('topic_id')->default(0);
                $table->json('data')->nullable();
                $table->string('username')->nullable();
                $table->unsignedInteger('uid')->nullable();
                $table->char('uid_type', 1)->nullable();
                $table->tinyInteger('annulled')->default(0);
                $table->dateTime('timestamp');
            });

            $this->ensureLegacyTable($legacy, '_search', function (Blueprint $table) {
                $table->string('object_type', 8);
                $table->unsignedInteger('object_id');
                $table->text('title');
                $table->text('content');
                $table->primary(['object_type', 'object_id']);
            });

            $this->ensureLegacyTable($osticket2, 'staff_two_factor', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->timestamps();
            });

            $this->ensureLegacyTable($osticket2, 'staff_auth_migrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->timestamp('migrated_at')->nullable();
                $table->timestamp('must_upgrade_after')->nullable();
                $table->string('upgrade_method', 32)->nullable();
                $table->timestamp('dismissed_migration_banner_at')->nullable();
                $table->timestamps();
            });

            if (! $osticket2->hasColumn('staff_auth_migrations', 'dismissed_migration_banner_at')) {
                $osticket2->table('staff_auth_migrations', function (Blueprint $table): void {
                    $table->timestamp('dismissed_migration_banner_at')->nullable()->after('upgrade_method');
                });
            }

            $this->ensureLegacyTable($osticket2, 'staff_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->string('theme', 16)->default('system');
                $table->string('language', 16)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->json('notifications')->nullable();
                $table->string('last_active_panel', 16)->default('scp');
                $table->string('default_scp_tab', 64)->nullable();
                $table->string('default_admin_tab', 64)->nullable();
                $table->timestamps();
            });

            $this->addMissingOsticket2Columns($osticket2, 'staff_preferences', [
                'last_active_panel' => fn (Blueprint $table) => $table->string('last_active_panel', 16)->default('scp'),
                'default_scp_tab' => fn (Blueprint $table) => $table->string('default_scp_tab', 64)->nullable(),
                'default_admin_tab' => fn (Blueprint $table) => $table->string('default_admin_tab', 64)->nullable(),
            ]);

            $this->ensureLegacyTable($osticket2, 'access_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->index();
                $table->string('action', 128);
                $table->string('subject_type', 64)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });

            $this->ensureLegacyTable($osticket2, 'admin_audit_log', function (Blueprint $table) {
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
            });

            foreach (['admin_audit_log', 'access_log', 'staff_preferences', 'staff_auth_migrations', 'staff_two_factor'] as $table) {
                $osticket2Connection->table($table)->delete();
            }

            foreach ([
                'role_has_permissions',
                'model_has_roles',
                'model_has_permissions',
                'roles',
                'permissions',
                'staff_dept_access',
                'session',
                'staff',
                'ticket',
                'thread',
                'thread_entry',
                'thread_event',
                '_search',
                'queue',
                'lock',
                'config',
                'draft',
                'event',
                'ticket_status',
            ] as $table) {
                $legacyConnection->table($table)->delete();
            }
        }
    }

    private function isRunningPhpUnit(): bool
    {
        return app()->runningUnitTests()
            || defined('PHPUNIT_COMPOSER_INSTALL')
            || defined('__PHPUNIT_PHAR__');
    }

    private function ensureLegacyTable($schema, string $table, \Closure $definition): void
    {
        if (! $schema->hasTable($table)) {
            $schema->create($table, $definition);
        }
    }

    private function legacyStaffTableNeedsRebuild(): bool
    {
        if (! Schema::connection('legacy')->hasTable('staff')) {
            return false;
        }

        $tableName = DB::connection('legacy')->getTablePrefix().'staff';
        $columns = DB::connection('legacy')->select("PRAGMA table_info('{$tableName}')");

        foreach ($columns as $column) {
            if (($column->name ?? null) !== 'dept_id') {
                continue;
            }

            return (int) ($column->notnull ?? 0) === 1 && ($column->dflt_value ?? null) === null;
        }

        return true;
    }

    /**
     * @param  array<string, \Closure>  $columns
     */
    private function addMissingOsticket2Columns($schema, string $table, array $columns): void
    {
        $missingColumns = array_filter(
            $columns,
            fn (string $column): bool => ! $schema->hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missingColumns === []) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($missingColumns): void {
            foreach ($missingColumns as $definition) {
                $definition($table);
            }
        });
    }
}
