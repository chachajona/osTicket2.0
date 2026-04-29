<?php

use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    foreach (['queue', 'ticket', 'ticket__cdata', 'ticket_status', 'ticket_priority', 'user', 'user_email'] as $table) {
        $schema->dropIfExists($table);
    }

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
});

afterEach(function (): void {
    $schema = Schema::connection('legacy');

    foreach (['queue', 'ticket', 'ticket__cdata', 'ticket_status', 'ticket_priority', 'user', 'user_email'] as $table) {
        $schema->dropIfExists($table);
    }
});

function queueReadStaff(array $attributes = []): Staff
{
    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => 70,
        'dept_id' => 1,
        'username' => 'queuereader',
        'firstname' => 'Queue',
        'lastname' => 'Reader',
        'email' => 'queue-reader@example.com',
        'passwd' => Hash::make('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->find($attributes['staff_id'] ?? 70);
}

test('queue show renders translated criteria rows through ticket rbac', function () {
    $staff = queueReadStaff();

    DB::connection('legacy')->table('queue')->insert([
        'id' => 10,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Open urgent',
        'config' => json_encode([
            ['status__state', 'exact', 'open'],
            ['cdata.priority', 'in', [2]],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket_status')->insert([
        ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
    ]);

    DB::connection('legacy')->table('ticket_priority')->insert([
        'priority_id' => 2,
        'priority' => 'High',
    ]);

    DB::connection('legacy')->table('user')->insert([
        'id' => 1,
        'default_email_id' => 5,
        'name' => 'Ada Lovelace',
    ]);

    DB::connection('legacy')->table('user_email')->insert([
        'id' => 5,
        'user_id' => 1,
        'address' => 'ada@example.com',
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 100, 'number' => '100100', 'user_id' => 1, 'status_id' => 1, 'dept_id' => 1, 'created' => '2026-04-20 10:00:00'],
        ['ticket_id' => 101, 'number' => '100101', 'user_id' => 1, 'status_id' => 2, 'dept_id' => 1, 'created' => '2026-04-20 11:00:00'],
        ['ticket_id' => 102, 'number' => '100102', 'user_id' => 1, 'status_id' => 1, 'dept_id' => 1, 'created' => '2026-04-20 12:00:00'],
        ['ticket_id' => 103, 'number' => '100103', 'user_id' => 1, 'status_id' => 1, 'dept_id' => 3, 'created' => '2026-04-20 13:00:00'],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 100, 'subject' => 'Visible ticket', 'priority' => '2'],
        ['ticket_id' => 101, 'subject' => 'Closed ticket', 'priority' => '2'],
        ['ticket_id' => 102, 'subject' => 'Low ticket', 'priority' => '1'],
        ['ticket_id' => 103, 'subject' => 'Forbidden ticket', 'priority' => '2'],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/10');

    $response->assertOk();
    $response->assertJsonPath('component', 'Scp/Queues/Show');
    $response->assertJsonPath('props.unsupported', false);
    $response->assertJsonPath('props.pagination.total', 1);
    $response->assertJsonPath('props.tickets.0.id', 100);
    $response->assertJsonPath('props.tickets.0.subject', 'Visible ticket');
    $response->assertJsonPath('props.tickets.0.priority', 'High');
});

test('private queues owned by another staff member are hidden', function () {
    $staff = queueReadStaff(['staff_id' => 71]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 11,
        'parent_id' => null,
        'staff_id' => 999,
        'flags' => 2,
        'title' => 'Other personal queue',
        'config' => null,
        'sort' => 1,
    ]);

    $this->actingAs($staff, 'staff')->get('/scp/queues/11')->assertNotFound();
});

test('unsupported queue criteria are reported without crashing', function () {
    $staff = queueReadStaff(['staff_id' => 72]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 12,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Unsupported queue',
        'config' => json_encode([
            ['unknown__field', 'exact', 'value'],
        ]),
        'sort' => 1,
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/12');

    $response->assertOk();
    $response->assertJsonPath('props.unsupported', true);
    $response->assertJsonPath('props.unsupportedReasons.0', 'Unsupported queue field [unknown__field].');
});

test('queue show supports legacy includes operator with option objects', function () {
    $staff = queueReadStaff(['staff_id' => 73]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 13,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'CCM source',
        'config' => json_encode([
            'criteria' => [
                ['source', 'includes', ['CCM' => 'CCM']],
            ],
            'conditions' => [],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 130, 'number' => '130130', 'dept_id' => 1, 'staff_id' => 0, 'source' => 'CCM'],
        ['ticket_id' => 131, 'number' => '130131', 'dept_id' => 1, 'staff_id' => 0, 'source' => 'MMS'],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 130, 'subject' => 'CCM ticket', 'priority' => '1'],
        ['ticket_id' => 131, 'subject' => 'MMS ticket', 'priority' => '1'],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/13');

    $response->assertOk();
    $response->assertJsonPath('props.unsupported', false);
    $response->assertJsonPath('props.pagination.total', 1);
    $response->assertJsonPath('props.tickets.0.id', 130);
});

test('queue filter chips narrow tickets by source priority state and date', function () {
    $staff = queueReadStaff(['staff_id' => 80]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 80,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'All open',
        'config' => json_encode([['status__state', 'exact', 'open']]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket_status')->insert([
        ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
    ]);

    DB::connection('legacy')->table('ticket_priority')->insert([
        ['priority_id' => 2, 'priority' => 'High', 'priority_urgency' => 1],
        ['priority_id' => 3, 'priority' => 'Low', 'priority_urgency' => 4],
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 800, 'number' => '800', 'dept_id' => 1, 'status_id' => 1, 'source' => 'Email', 'created' => '2026-04-26 09:00:00'],
        ['ticket_id' => 801, 'number' => '801', 'dept_id' => 1, 'status_id' => 1, 'source' => 'Web', 'created' => '2026-04-26 10:00:00'],
        ['ticket_id' => 802, 'number' => '802', 'dept_id' => 1, 'status_id' => 1, 'source' => 'Phone', 'created' => '2026-01-15 09:00:00'],
        ['ticket_id' => 803, 'number' => '803', 'dept_id' => 1, 'status_id' => 1, 'source' => 'Email', 'created' => '2026-04-26 11:00:00'],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 800, 'subject' => 'Email High', 'priority' => '2'],
        ['ticket_id' => 801, 'subject' => 'Web High', 'priority' => '2'],
        ['ticket_id' => 802, 'subject' => 'Phone Low', 'priority' => '3'],
        ['ticket_id' => 803, 'subject' => 'Email Low', 'priority' => '3'],
    ]);

    $sourceResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/80?source[]=Email');

    $sourceResponse->assertOk();
    $sourceResponse->assertJsonPath('props.pagination.total', 2);
    $sourceResponse->assertJsonPath('props.filters.source.0', 'Email');

    $priorityResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/80?priority[]=2');

    $priorityResponse->assertJsonPath('props.pagination.total', 2);
    expect(collect($priorityResponse->json('props.tickets'))->pluck('id')->all())
        ->toEqualCanonicalizing([800, 801]);

    $bothResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/80?source[]=Email&priority[]=2');

    $bothResponse->assertJsonPath('props.pagination.total', 1);
    $bothResponse->assertJsonPath('props.tickets.0.id', 800);

    $stateResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/80?state[]=closed');

    // The queue itself filters to state=open via criteria, so a closed-state filter yields zero.
    $stateResponse->assertJsonPath('props.pagination.total', 0);

    $dateResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/80?created_from=2026-04-01&created_to=2026-04-30');

    $dateResponse->assertJsonPath('props.pagination.total', 3);
    expect(collect($dateResponse->json('props.tickets'))->pluck('id')->all())
        ->not->toContain(802);
});

test('queue sort orders rows server-side', function () {
    $staff = queueReadStaff(['staff_id' => 81]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 81,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Sortable',
        'config' => null,
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 900, 'number' => '900', 'dept_id' => 1, 'staff_id' => 0, 'created' => '2026-04-20 09:00:00'],
        ['ticket_id' => 901, 'number' => '901', 'dept_id' => 1, 'staff_id' => 0, 'created' => '2026-04-22 09:00:00'],
        ['ticket_id' => 902, 'number' => '902', 'dept_id' => 1, 'staff_id' => 0, 'created' => '2026-04-21 09:00:00'],
        ['ticket_id' => 903, 'number' => '903', 'dept_id' => 1, 'staff_id' => 0, 'created' => '2026-04-23 09:00:00'],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 900, 'subject' => 'A', 'priority' => '3'],
        ['ticket_id' => 901, 'subject' => 'B', 'priority' => '1'],
        ['ticket_id' => 902, 'subject' => 'C', 'priority' => '2'],
        ['ticket_id' => 903, 'subject' => 'D', 'priority' => '10'],
    ]);

    $createdAsc = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/81?sort=created&dir=asc');

    expect(collect($createdAsc->json('props.tickets'))->pluck('id')->all())
        ->toBe([900, 902, 901, 903]);

    $createdDesc = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/81?sort=created&dir=desc');

    expect(collect($createdDesc->json('props.tickets'))->pluck('id')->all())
        ->toBe([903, 901, 902, 900]);

    $priorityAsc = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/81?sort=priority&dir=asc');

    expect(collect($priorityAsc->json('props.tickets'))->pluck('id')->all())
        ->toBe([901, 902, 900, 903]);

    $numberDesc = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/81?sort=number&dir=desc');

    expect(collect($numberDesc->json('props.tickets'))->pluck('id')->all())
        ->toBe([903, 902, 901, 900]);
});

test('queue read degrades to empty rows when the legacy ticket query fails', function () {
    $staff = queueReadStaff(['staff_id' => 83]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 83,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Broken legacy query',
        'config' => json_encode([
            ['unknown__field', 'exact', 'value'],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        'ticket_id' => 830,
        'number' => '830',
        'dept_id' => 1,
        'created' => '2026-04-25 09:00:00',
    ]);

    Schema::connection('legacy')->dropIfExists('ticket__cdata');

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/83?sort=priority&dir=asc');

    $response->assertOk();
    $response->assertJsonPath('props.pagination.total', 0);
    $response->assertJsonPath('props.tickets', []);
    $response->assertJsonPath('props.unsupported', true);
    $response->assertJsonPath('props.unsupportedReasons.0', 'Unsupported queue field [unknown__field].');
});

test('queue filters do not bypass ticket rbac', function () {
    $staff = queueReadStaff(['staff_id' => 82, 'dept_id' => 1]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 82,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'RBAC test',
        'config' => null,
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 1000, 'number' => '1000', 'dept_id' => 1, 'staff_id' => 0, 'source' => 'Email', 'created' => '2026-04-25 09:00:00'],
        ['ticket_id' => 1001, 'number' => '1001', 'dept_id' => 9, 'staff_id' => 0, 'source' => 'Email', 'created' => '2026-04-25 09:00:00'],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 1000, 'subject' => 'mine', 'priority' => '2'],
        ['ticket_id' => 1001, 'subject' => 'forbidden', 'priority' => '2'],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/82?source[]=Email');

    $response->assertJsonPath('props.pagination.total', 1);
    $response->assertJsonPath('props.tickets.0.id', 1000);
});

test('assigned to me queue filters by authenticated staff', function () {
    $staff = queueReadStaff(['staff_id' => 74]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 14,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Assigned to me',
        'config' => json_encode([
            'criteria' => [
                ['assignee', 'includes', ['M' => 'Me']],
            ],
            'conditions' => [],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 140, 'number' => '140140', 'dept_id' => 1, 'staff_id' => 74],
        ['ticket_id' => 141, 'number' => '140141', 'dept_id' => 1, 'staff_id' => 75],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/14');

    $response->assertOk();
    $response->assertJsonPath('props.unsupported', false);
    $response->assertJsonPath('props.pagination.total', 1);
    $response->assertJsonPath('props.tickets.0.id', 140);

    $sortedResponse = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/14?sort=assignee&dir=asc');

    $sortedResponse->assertOk();
    $sortedResponse->assertJsonPath('props.pagination.total', 1);
    $sortedResponse->assertJsonPath('props.tickets.0.id', 140);
});

test('assigned to my teams queue returns no tickets when staff has no teams', function () {
    $staff = queueReadStaff(['staff_id' => 75]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 15,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Assigned to my teams',
        'config' => json_encode([
            'criteria' => [
                ['assignee', 'includes', ['T' => 'Teams']],
            ],
            'conditions' => [],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 150, 'number' => '150150', 'dept_id' => 1, 'team_id' => 12],
        ['ticket_id' => 151, 'number' => '150151', 'dept_id' => 1, 'staff_id' => 75],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/15');

    $response->assertOk();
    $response->assertJsonPath('props.unsupported', false);
    $response->assertJsonPath('props.pagination.total', 0);
});

test('assignee exclusion with no recognized targets leaves queue unconstrained', function () {
    $staff = queueReadStaff(['staff_id' => 76]);

    DB::connection('legacy')->table('queue')->insert([
        'id' => 16,
        'parent_id' => null,
        'staff_id' => 0,
        'flags' => 3,
        'title' => 'Unknown assignee exclusion',
        'config' => json_encode([
            'criteria' => [
                ['assignee', '!includes', ['D' => 'Department']],
            ],
            'conditions' => [],
        ]),
        'sort' => 1,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 160, 'number' => '160160', 'dept_id' => 1, 'staff_id' => 76],
        ['ticket_id' => 161, 'number' => '160161', 'dept_id' => 1, 'staff_id' => 0],
    ]);

    DB::connection('legacy')->table('ticket__cdata')->insert([
        ['ticket_id' => 160, 'subject' => 'Assigned', 'priority' => '1'],
        ['ticket_id' => 161, 'subject' => 'Unassigned', 'priority' => '1'],
    ]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/queues/16');

    $response->assertOk();
    $response->assertJsonPath('props.unsupported', false);
    $response->assertJsonPath('props.pagination.total', 2);
});
