<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    ensureMaintenanceLegacyTables();

    DB::connection('legacy')->table('syslog')->delete();
    DB::connection('legacy')->table('draft')->delete();
});

test('system purge logs deletes old rows across multiple chunks', function () {
    $oldLogs = [];

    foreach (range(1, 1005) as $i) {
        $oldLogs[] = [
            'log_type' => 'Warning',
            'title' => "Old log {$i}",
            'log' => 'Old message',
            'logger' => 'test',
            'ip_address' => '127.0.0.1',
            'created' => now()->subDays(120),
            'updated' => now()->subDays(120),
        ];
    }

    DB::connection('legacy')->table('syslog')->insert($oldLogs);
    DB::connection('legacy')->table('syslog')->insert([
        'log_type' => 'Warning',
        'title' => 'Recent log',
        'log' => 'Recent message',
        'logger' => 'test',
        'ip_address' => '127.0.0.1',
        'created' => now()->subDays(5),
        'updated' => now()->subDays(5),
    ]);

    $this->artisan('system:purge-logs', ['--days' => 90])
        ->assertSuccessful();

    expect(DB::connection('legacy')->table('syslog')->count())->toBe(1);
    expect(DB::connection('legacy')->table('syslog')->value('title'))->toBe('Recent log');
});

test('drafts cleanup deletes old rows across multiple chunks', function () {
    $oldDrafts = [];

    foreach (range(1, 1005) as $i) {
        $oldDrafts[] = [
            'staff_id' => 1,
            'namespace' => 'reply',
            'body' => "Old draft {$i}",
            'created' => now()->subDays(45),
            'updated' => now()->subDays(45),
        ];
    }

    DB::connection('legacy')->table('draft')->insert($oldDrafts);
    DB::connection('legacy')->table('draft')->insert([
        'staff_id' => 1,
        'namespace' => 'reply',
        'body' => 'Recent draft',
        'created' => now()->subDays(2),
        'updated' => now()->subDays(2),
    ]);

    $this->artisan('drafts:cleanup', ['--days' => 30])
        ->assertSuccessful();

    expect(DB::connection('legacy')->table('draft')->count())->toBe(1);
    expect(DB::connection('legacy')->table('draft')->value('body'))->toBe('Recent draft');
});

function ensureMaintenanceLegacyTables(): void
{
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('syslog')) {
        $schema->create('syslog', function (Blueprint $table) {
            $table->increments('log_id');
            $table->string('log_type');
            $table->string('title');
            $table->text('log');
            $table->string('logger');
            $table->string('ip_address');
            $table->dateTime('created');
            $table->dateTime('updated');
        });
    }

    if (! $schema->hasTable('draft')) {
        $schema->create('draft', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('staff_id');
            $table->string('namespace', 32)->default('');
            $table->text('body');
            $table->text('extra')->nullable();
            $table->timestamp('created')->useCurrent();
            $table->timestamp('updated')->nullable();
        });
    }
}
