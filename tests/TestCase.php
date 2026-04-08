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
        if (! $schema->hasTable($table)) {
            $schema->create($table, $definition);
        }
    }
}
