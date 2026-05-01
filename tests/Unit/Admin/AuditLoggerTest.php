<?php

use App\Models\Staff;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FakeAuditedSubject extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'fake_audit_subjects';

    public $timestamps = false;

    protected $guarded = [];

    protected static array $auditExcluded = ['password'];
}

beforeEach(function (): void {
    Schema::connection('osticket2')->dropIfExists('admin_audit_log');
    Schema::connection('osticket2')->dropIfExists('fake_audit_subjects');

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

    Schema::connection('osticket2')->create('fake_audit_subjects', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name')->nullable();
        $table->string('password')->nullable();
    });
});

afterEach(function (): void {
    Schema::connection('osticket2')->dropIfExists('fake_audit_subjects');
    Schema::connection('osticket2')->dropIfExists('admin_audit_log');
});

test('record captures create events and request context', function (): void {
    $request = Request::create('/admin/roles', 'POST', server: [
        'HTTP_USER_AGENT' => 'phpunit',
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_REQUEST_ID' => 'req-123',
    ]);
    app()->instance('request', $request);

    $actor = Staff::create([
        'username' => 'admin',
        'firstname' => 'Admin',
        'lastname' => 'User',
        'email' => 'admin@example.com',
        'passwd' => 'hashed',
    ]);
    $subject = FakeAuditedSubject::create(['name' => 'Manager']);

    $log = app(AuditLogger::class)->record(
        $actor,
        'role.create',
        $subject,
        before: null,
        after: $subject->only(['name']),
    );

    expect($log->actor_id)->toBe((int) $actor->staff_id)
        ->and($log->action)->toBe('role.create')
        ->and($log->subject_type)->toBe('FakeAuditedSubject')
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->before)->toBeNull()
        ->and($log->after)->toBe(['name' => 'Manager'])
        ->and($log->metadata)->toBe(['request_id' => 'req-123'])
        ->and($log->ip_address)->toBe('10.0.0.1')
        ->and($log->user_agent)->toBe('phpunit');
});

test('record redacts excluded fields for updates', function (): void {
    app()->instance('request', Request::create('/admin/staff/1', 'PATCH'));

    $actor = Staff::create([
        'username' => 'auditor',
        'firstname' => 'Audit',
        'lastname' => 'User',
        'email' => 'auditor@example.com',
        'passwd' => 'hashed',
    ]);
    $subject = FakeAuditedSubject::create(['name' => 'Bob', 'password' => 'secret']);

    $log = app(AuditLogger::class)->record(
        $actor,
        'staff.update',
        $subject,
        before: ['name' => 'Bob', 'password' => 'old-secret'],
        after: ['name' => 'Bob', 'password' => 'new-secret'],
        metadata: ['source' => 'test'],
    );

    expect($log->before)->toBe(['name' => 'Bob', 'password' => '[redacted]'])
        ->and($log->after)->toBe(['name' => 'Bob', 'password' => '[redacted]'])
        ->and($log->metadata)->toBe(['source' => 'test']);
});

test('record supports delete events', function (): void {
    app()->instance('request', Request::create('/admin/roles/7', 'DELETE'));

    $actor = Staff::create([
        'username' => 'deleter',
        'firstname' => 'Delete',
        'lastname' => 'User',
        'email' => 'deleter@example.com',
        'passwd' => 'hashed',
    ]);
    $subject = FakeAuditedSubject::create(['name' => 'Doomed']);

    $log = app(AuditLogger::class)->record(
        $actor,
        'role.delete',
        $subject,
        before: ['name' => 'Doomed'],
        after: null,
    );

    expect($log->before)->toBe(['name' => 'Doomed'])
        ->and($log->after)->toBeNull();
});
