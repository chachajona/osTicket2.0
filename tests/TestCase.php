<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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

            $legacy->dropIfExists('staff_dept_access');
            $legacy->dropIfExists('staff');
            $legacy->dropIfExists('session');

            $legacy->create('staff', function ($table) {
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

            $legacy->create('staff_dept_access', function ($table) {
                $table->unsignedInteger('staff_id');
                $table->unsignedInteger('dept_id');
                $table->unsignedInteger('role_id')->default(0);
                $table->unsignedInteger('flags')->default(0);
                $table->primary(['staff_id', 'dept_id']);
            });

            $legacy->create('session', function ($table) {
                $table->string('session_id', 64)->primary();
                $table->unsignedInteger('user_id')->default(0);
                $table->text('session_data')->nullable();
                $table->dateTime('session_expire')->nullable();
            });
        }
    }
}
