<?php

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

        if (config('database.connections.legacy.driver') === 'sqlite') {
            $dbPath = config('database.connections.legacy.database');

            if ($dbPath && $dbPath !== ':memory:') {
                touch($dbPath);
            }

            $legacy = Schema::connection('legacy');
            $legacyConnection = DB::connection('legacy');
            $osticket2 = Schema::connection('osticket2');
            $osticket2Connection = DB::connection('osticket2');

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
                $table->timestamps();
            });

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
            ] as $table) {
                $legacyConnection->table($table)->delete();
            }
        }
    }

    private function ensureLegacyTable($schema, string $table, \Closure $definition): void
    {
        if ($schema->hasTable($table)) {
            $schema->drop($table);
        }

        $schema->create($table, $definition);
    }
}
