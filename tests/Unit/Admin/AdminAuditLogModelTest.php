<?php

use App\Models\Admin\AdminAuditLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::connection('osticket2')->dropIfExists('admin_audit_log');

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
    });
});

afterEach(function (): void {
    Schema::connection('osticket2')->dropIfExists('admin_audit_log');
});

test('admin audit log model uses osticket2 connection', function (): void {
    $model = new AdminAuditLog;

    expect($model->getConnectionName())->toBe('osticket2')
        ->and($model->getTable())->toBe('admin_audit_log');
});

test('admin audit log casts before after and metadata as arrays', function (): void {
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

    $reloaded = AdminAuditLog::findOrFail($log->id);

    expect($reloaded->before)->toBe(['name' => 'A'])
        ->and($reloaded->after)->toBe(['name' => 'B'])
        ->and($reloaded->metadata)->toBe(['request_id' => 'abc']);
});

test('pruneBefore deletes older audit rows', function (): void {
    DB::connection('osticket2')->table('admin_audit_log')->insert([
        [
            'actor_id' => 1,
            'action' => 'role.update',
            'subject_type' => 'Role',
            'subject_id' => 10,
            'before' => json_encode(['name' => 'Old'], JSON_THROW_ON_ERROR),
            'after' => json_encode(['name' => 'New'], JSON_THROW_ON_ERROR),
            'metadata' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => '2026-04-01 00:00:00',
        ],
        [
            'actor_id' => 2,
            'action' => 'role.create',
            'subject_type' => 'Role',
            'subject_id' => 11,
            'before' => null,
            'after' => json_encode(['name' => 'Current'], JSON_THROW_ON_ERROR),
            'metadata' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => '2026-05-01 00:00:00',
        ],
    ]);

    $deleted = AdminAuditLog::pruneBefore(new DateTime('2026-04-15 00:00:00'));

    expect($deleted)->toBe(1)
        ->and(AdminAuditLog::query()->pluck('subject_id')->all())->toBe([11]);
});
