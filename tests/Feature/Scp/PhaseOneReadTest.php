<?php

use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    foreach ([
        '_search', 'attachment', 'file_chunk', 'file', 'thread_referral', 'thread_collaborator',
        'thread_event', 'event', 'thread_entry', 'thread', 'queue_export', 'queue',
        'form_field', 'ticket__cdata', 'ticket_status', 'ticket_priority', 'user_email', 'user', 'department', 'ticket',
    ] as $table) {
        $schema->dropIfExists($table);
    }

    $schema->create('ticket', function (Blueprint $table): void {
        $table->unsignedInteger('ticket_id')->primary();
        $table->string('number')->default('');
        $table->unsignedInteger('user_id')->default(0);
        $table->unsignedInteger('status_id')->default(1);
        $table->unsignedInteger('dept_id')->default(0);
        $table->unsignedInteger('staff_id')->default(0);
        $table->unsignedInteger('team_id')->default(0);
        $table->unsignedInteger('topic_id')->default(0);
        $table->unsignedInteger('sla_id')->default(0);
        $table->unsignedInteger('email_id')->default(0);
        $table->string('source')->default('');
        $table->string('ip_address')->default('');
        $table->tinyInteger('isoverdue')->default(0);
        $table->tinyInteger('isanswered')->default(0);
        $table->dateTime('duedate')->nullable();
        $table->dateTime('closed')->nullable();
        $table->dateTime('lastupdate')->nullable();
        $table->dateTime('lastmessage')->nullable();
        $table->dateTime('lastresponse')->nullable();
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('ticket__cdata', function (Blueprint $table): void {
        $table->unsignedInteger('ticket_id')->primary();
        $table->string('subject')->nullable();
        $table->string('priority')->nullable();
    });

    $schema->create('ticket_status', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->string('name');
        $table->string('state');
        $table->unsignedInteger('mode')->default(0);
        $table->unsignedInteger('flags')->default(0);
        $table->unsignedInteger('sort')->default(0);
        $table->text('properties')->nullable();
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('ticket_priority', function (Blueprint $table): void {
        $table->unsignedInteger('priority_id')->primary();
        $table->string('priority');
        $table->string('priority_desc')->default('');
        $table->string('priority_color')->default('');
        $table->unsignedInteger('priority_urgency')->default(0);
        $table->tinyInteger('ispublic')->default(1);
    });

    $schema->create('department', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('tpl_id')->default(0);
        $table->unsignedInteger('sla_id')->default(0);
        $table->unsignedInteger('manager_id')->default(0);
        $table->string('name')->default('');
        $table->text('signature')->nullable();
        $table->tinyInteger('ispublic')->default(1);
    });

    $schema->create('user', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('org_id')->default(0);
        $table->unsignedInteger('default_email_id')->default(0);
        $table->string('name')->default('');
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('user_email', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('user_id')->default(0);
        $table->unsignedInteger('flags')->default(0);
        $table->string('address')->default('');
    });

    $schema->create('thread', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('object_id');
        $table->string('object_type', 1);
        $table->dateTime('created')->nullable();
    });

    $schema->create('thread_entry', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('thread_id');
        $table->unsignedInteger('staff_id')->default(0);
        $table->string('type', 1)->default('M');
        $table->text('body')->nullable();
        $table->string('format')->default('html');
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('event', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->string('name');
    });

    $schema->create('thread_event', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('thread_id');
        $table->string('thread_type', 1)->default('T');
        $table->unsignedInteger('event_id')->default(0);
        $table->unsignedInteger('staff_id')->default(0);
        $table->unsignedInteger('team_id')->default(0);
        $table->unsignedInteger('dept_id')->default(0);
        $table->unsignedInteger('topic_id')->default(0);
        $table->text('data')->nullable();
        $table->string('username')->nullable();
        $table->unsignedInteger('uid')->default(0);
        $table->string('uid_type')->nullable();
        $table->tinyInteger('annulled')->default(0);
        $table->dateTime('timestamp')->nullable();
    });

    $schema->create('thread_collaborator', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('flags')->default(0);
        $table->unsignedInteger('thread_id');
        $table->unsignedInteger('user_id');
        $table->string('role')->nullable();
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('thread_referral', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('thread_id');
        $table->unsignedInteger('object_id');
        $table->string('object_type');
        $table->dateTime('created')->nullable();
    });

    $schema->create('file', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->string('bk', 8)->default('D');
        $table->string('type')->nullable();
        $table->unsignedInteger('size')->default(0);
        $table->string('name')->nullable();
        $table->string('key')->nullable();
        $table->string('signature')->nullable();
        $table->string('ft')->nullable();
        $table->string('mime')->nullable();
        $table->text('attrs')->nullable();
        $table->dateTime('created')->nullable();
    });

    $schema->create('file_chunk', function (Blueprint $table): void {
        $table->unsignedInteger('file_id');
        $table->unsignedInteger('chunk_id');
        $table->binary('filedata');
        $table->primary(['file_id', 'chunk_id']);
    });

    $schema->create('attachment', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('file_id');
        $table->string('object_type');
        $table->unsignedInteger('object_id');
        $table->string('name')->nullable();
        $table->tinyInteger('inline')->default(0);
    });

    $schema->create('_search', function (Blueprint $table): void {
        $table->string('object_type', 1);
        $table->unsignedInteger('object_id');
        $table->text('title')->nullable();
        $table->text('content')->nullable();
    });

    $schema->create('queue', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('parent_id')->nullable();
        $table->unsignedInteger('staff_id')->default(0);
        $table->unsignedInteger('flags')->default(0);
        $table->string('title')->default('');
        $table->text('config')->nullable();
        $table->unsignedInteger('sort')->default(0);
        $table->dateTime('created')->nullable();
        $table->dateTime('updated')->nullable();
    });

    $schema->create('queue_export', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('queue_id');
        $table->string('name')->nullable();
        $table->text('config')->nullable();
    });

    $schema->create('form_field', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->string('name')->nullable();
        $table->string('label')->nullable();
    });

    seedPhaseOneReadFixture();
});

afterEach(function (): void {
    $schema = Schema::connection('legacy');

    foreach (['attachment', 'file_chunk', 'file'] as $table) {
        if ($schema->hasTable($table)) {
            DB::connection('legacy')->table($table)->delete();
        }
    }

    foreach ([
        '_search', 'thread_referral', 'thread_collaborator',
        'thread_event', 'event', 'thread_entry', 'thread', 'queue_export', 'queue',
        'form_field', 'ticket__cdata', 'ticket_status', 'ticket_priority', 'user_email', 'user', 'department', 'ticket',
    ] as $table) {
        $schema->dropIfExists($table);
    }
});

function phaseOneStaff(array $attributes = []): Staff
{
    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => 80,
        'dept_id' => 1,
        'username' => 'phaseone',
        'firstname' => 'Phase',
        'lastname' => 'One',
        'email' => 'phase-one@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->find($attributes['staff_id'] ?? 80);
}

function seedPhaseOneReadFixture(): void
{
    DB::connection('legacy')->table('ticket_status')->insert([
        'id' => 1,
        'name' => 'Open',
        'state' => 'open',
    ]);

    DB::connection('legacy')->table('ticket_priority')->insert([
        'priority_id' => 2,
        'priority' => 'High',
    ]);

    DB::connection('legacy')->table('department')->insert([
        'id' => 1,
        'name' => 'Support',
    ]);

    DB::connection('legacy')->table('user')->insert([
        ['id' => 1, 'default_email_id' => 1, 'name' => 'Grace Hopper'],
        ['id' => 2, 'default_email_id' => 2, 'name' => 'Hidden User'],
    ]);

    DB::connection('legacy')->table('user_email')->insert([
        ['id' => 1, 'user_id' => 1, 'address' => 'grace@example.com'],
        ['id' => 2, 'user_id' => 2, 'address' => 'hidden@example.com'],
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 200, 'number' => '200200', 'user_id' => 1, 'status_id' => 1, 'dept_id' => 1, 'staff_id' => 80, 'sla_id' => 5, 'created' => '2026-04-20 10:00:00', 'updated' => '2026-04-20 10:30:00'],
        ['ticket_id' => 201, 'number' => '200201', 'user_id' => 2, 'status_id' => 1, 'dept_id' => 3, 'staff_id' => 0, 'sla_id' => 0, 'created' => '2026-04-20 11:00:00', 'updated' => null],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 200, 'subject' => 'Visible searchable ticket', 'priority' => '2'],
        ['ticket_id' => 201, 'subject' => 'Hidden searchable ticket', 'priority' => '2'],
    ]);

    DB::connection('legacy')->table('thread')->insert([
        'id' => 300,
        'object_id' => 200,
        'object_type' => 'T',
        'created' => '2026-04-20 10:00:00',
    ]);

    DB::connection('legacy')->table('thread_entry')->insert([
        'id' => 400,
        'thread_id' => 300,
        'staff_id' => 80,
        'type' => 'M',
        'body' => 'Initial message body',
        'format' => 'html',
        'created' => '2026-04-20 10:01:00',
    ]);

    DB::connection('legacy')->table('event')->insert(['id' => 1, 'name' => 'created']);
    DB::connection('legacy')->table('thread_event')->insert([
        'id' => 500,
        'thread_id' => 300,
        'event_id' => 1,
        'staff_id' => 80,
        'username' => 'phaseone',
        'data' => '{}',
        'timestamp' => '2026-04-20 10:02:00',
    ]);

    DB::connection('legacy')->table('thread_collaborator')->insert([
        'id' => 600,
        'thread_id' => 300,
        'user_id' => 1,
        'role' => 'cc',
    ]);

    DB::connection('legacy')->table('thread_referral')->insert([
        'id' => 700,
        'thread_id' => 300,
        'object_id' => 9,
        'object_type' => 'D',
        'created' => '2026-04-20 10:03:00',
    ]);

    DB::connection('legacy')->table('file')->insert([
        'id' => 800,
        'bk' => 'D',
        'size' => 11,
        'name' => 'hello.txt',
        'mime' => 'text/plain',
    ]);

    DB::connection('legacy')->table('file_chunk')->insert([
        ['file_id' => 800, 'chunk_id' => 1, 'filedata' => 'hello '],
        ['file_id' => 800, 'chunk_id' => 2, 'filedata' => 'world'],
    ]);

    DB::connection('legacy')->table('attachment')->insert([
        'id' => 900,
        'file_id' => 800,
        'object_type' => 'H',
        'object_id' => 400,
        'name' => 'hello.txt',
        'inline' => 0,
    ]);

    DB::connection('legacy')->table('_search')->insert([
        ['object_type' => 'T', 'object_id' => 200, 'title' => 'Visible searchable ticket', 'content' => 'needle'],
        ['object_type' => 'T', 'object_id' => 201, 'title' => 'Hidden searchable ticket', 'content' => 'needle'],
    ]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 1000,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Open',
        'config' => json_encode([['status__state', 'exact', 'open']]),
    ]);

    DB::connection('legacy')->table('queue_export')->insert([
        'id' => 1001,
        'queue_id' => 1000,
        'name' => 'Default',
        'config' => json_encode(['number', 'subject', 'from', 'priority']),
    ]);

    DB::connection('legacy')->table('form_field')->insert([
        ['id' => 1, 'name' => 'subject', 'label' => 'Subject'],
        ['id' => 2, 'name' => 'priority', 'label' => 'Priority'],
    ]);
}

test('ticket detail includes timeline attachments collaborators referrals and custom fields', function () {
    $staff = phaseOneStaff();

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/tickets/200');

    $response->assertOk();
    $response->assertJsonPath('component', 'Scp/Tickets/Show');
    $response->assertJsonPath('props.ticket.number', '200200');
    $response->assertJsonPath('props.customFields.Subject', 'Visible searchable ticket');
    $response->assertJsonPath('props.timeline.0.body', 'Initial message body');
    $response->assertJsonPath('props.attachments.0.file_id', 800);
    $response->assertJsonPath('props.collaborators.0.email', 'grace@example.com');
    $response->assertJsonPath('props.referrals.0.object_type', 'D');
});

test('search returns only rbac-visible tickets', function () {
    $staff = phaseOneStaff(['staff_id' => 81]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/search?q=needle');

    $response->assertOk();
    $response->assertJsonPath('props.results.0.id', 200);
    expect($response->json('props.results'))->toHaveCount(1);
});

test('dashboard uses live visible open ticket counts', function () {
    $this->travelTo('2026-04-28 12:00:00');
    $staff = phaseOneStaff();

    DB::connection('legacy')->table('ticket_status')->insert([
        'id' => 2,
        'name' => 'Closed',
        'state' => 'closed',
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        [
            'ticket_id' => 202,
            'number' => '200202',
            'user_id' => 1,
            'status_id' => 1,
            'dept_id' => 1,
            'staff_id' => 0,
            'team_id' => 0,
            'isoverdue' => 1,
            'source' => 'Email',
            'created' => '2026-04-20 12:00:00',
            'closed' => null,
        ],
        [
            'ticket_id' => 203,
            'number' => '200203',
            'user_id' => 1,
            'status_id' => 2,
            'dept_id' => 1,
            'staff_id' => 80,
            'team_id' => 0,
            'isoverdue' => 0,
            'source' => 'Email',
            'created' => '2026-03-20 12:00:00',
            'closed' => '2026-04-05 12:00:00',
        ],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp');

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.metrics.open', 2);
    $response->assertJsonPath('props.metrics.assignedToMe', 1);
    $response->assertJsonPath('props.metrics.unassigned', 1);
    $response->assertJsonPath('props.metrics.overdue', 1);
    $response->assertJsonPath('props.metrics.trend.open.previous', 1);
    $response->assertJsonPath('props.metrics.trend.open.percent', 100);
    $response->assertJsonPath('props.metrics.trend.open.direction', 'up');
    $response->assertJsonPath('props.metrics.trend.assignedToMe.direction', 'flat');
    $response->assertJsonPath('props.metrics.trend.unassigned.direction', 'new');
    $response->assertJsonPath('props.metrics.statusComparison.rangeStart', '2025-11-01');
    $response->assertJsonPath('props.metrics.statusComparison.rangeEnd', '2026-04-28');
    $response->assertJsonPath('props.metrics.statusComparison.openTotal', 2);
    $response->assertJsonPath('props.metrics.statusComparison.solvedTotal', 1);
    $response->assertJsonPath('props.metrics.statusComparison.months.5.month', '2026-04-01');
    $response->assertJsonPath('props.metrics.statusComparison.months.5.open', 2);
    $response->assertJsonPath('props.metrics.statusComparison.months.5.solved', 1);
    $response->assertJsonPath('props.metrics.channelDistribution.total', 3);
    $response->assertJsonPath('props.metrics.channelDistribution.channels.0.key', 'email');
    $response->assertJsonPath('props.metrics.channelDistribution.channels.0.label', 'Email');
    $response->assertJsonPath('props.metrics.channelDistribution.channels.0.count', 2);
    $response->assertJsonPath('props.metrics.recentActivity.0.ticket_number', '200200');
});

test('chunked attachment download streams legacy bytes', function () {
    $staff = phaseOneStaff(['staff_id' => 82]);

    $response = $this->actingAs($staff, 'staff')->get('/scp/attachments/800');

    $response->assertOk();
    expect($response->streamedContent())->toBe('hello world');
});

test('queue csv export uses configured fields and rbac-visible rows', function () {
    $staff = phaseOneStaff(['staff_id' => 83]);

    $response = $this->actingAs($staff, 'staff')->get('/scp/queues/1000/export');

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Number,Subject,From,Priority')
        ->and($csv)->toContain('200200,"Visible searchable ticket","Grace Hopper",High')
        ->and($csv)->not->toContain('200201');
});
