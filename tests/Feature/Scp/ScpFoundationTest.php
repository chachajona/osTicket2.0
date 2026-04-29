<?php

use App\Models\Staff;
use App\Models\Ticket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('ticket')) {
        $schema->create('ticket', function (Blueprint $table): void {
            $table->unsignedInteger('ticket_id')->primary();
            $table->string('number')->default('');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('status_id')->default(1);
            $table->unsignedInteger('dept_id')->default(0);
            $table->unsignedInteger('staff_id')->default(0);
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
    }

    foreach ([
        'number' => fn (Blueprint $table) => $table->string('number')->default(''),
        'dept_id' => fn (Blueprint $table) => $table->unsignedInteger('dept_id')->default(0),
        'staff_id' => fn (Blueprint $table) => $table->unsignedInteger('staff_id')->default(0),
        'topic_id' => fn (Blueprint $table) => $table->unsignedInteger('topic_id')->default(0),
        'status_id' => fn (Blueprint $table) => $table->unsignedInteger('status_id')->default(1),
        'source' => fn (Blueprint $table) => $table->string('source')->default(''),
        'isoverdue' => fn (Blueprint $table) => $table->tinyInteger('isoverdue')->default(0),
        'created' => fn (Blueprint $table) => $table->dateTime('created')->nullable(),
        'updated' => fn (Blueprint $table) => $table->dateTime('updated')->nullable(),
        'closed' => fn (Blueprint $table) => $table->dateTime('closed')->nullable(),
    ] as $column => $definition) {
        if (! $schema->hasColumn('ticket', $column)) {
            $schema->table('ticket', $definition);
        }
    }

    if ($schema->hasTable('ticket__cdata')) {
        foreach ([
            'subject' => fn (Blueprint $table) => $table->string('subject')->nullable(),
            'priority' => fn (Blueprint $table) => $table->string('priority')->nullable(),
        ] as $column => $definition) {
            if (! $schema->hasColumn('ticket__cdata', $column)) {
                $schema->table('ticket__cdata', $definition);
            }
        }
    }

    DB::connection('legacy')->table('ticket')->delete();
});

afterEach(function (): void {
    if (Schema::connection('legacy')->hasTable('ticket')) {
        Schema::connection('legacy')->drop('ticket');
    }
});

function scpStaff(array $attributes = []): Staff
{
    DB::connection('legacy')->table('staff')->insert(array_merge([
        'staff_id' => 50,
        'dept_id' => 1,
        'username' => 'scpstaff',
        'firstname' => 'SCP',
        'lastname' => 'Staff',
        'email' => 'scp@example.com',
        'passwd' => bcrypt('password'),
        'isactive' => 1,
        'isadmin' => 0,
        'created' => now(),
    ], $attributes));

    return Staff::on('legacy')->find($attributes['staff_id'] ?? 50);
}

test('ticket queries are scoped to authenticated staff department access', function () {
    $staff = scpStaff(['staff_id' => 51, 'dept_id' => 1]);

    DB::connection('legacy')->table('staff_dept_access')->insert([
        'staff_id' => 51,
        'dept_id' => 2,
        'role_id' => 1,
        'flags' => 0,
    ]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 1, 'number' => 'A', 'dept_id' => 1, 'staff_id' => 0],
        ['ticket_id' => 2, 'number' => 'B', 'dept_id' => 2, 'staff_id' => 0],
        ['ticket_id' => 3, 'number' => 'C', 'dept_id' => 3, 'staff_id' => 0],
    ]);

    Auth::guard('staff')->login($staff);

    expect(Ticket::query()->pluck('ticket_id')->all())->toBe([1, 2]);
});

test('admin ticket queries are not department scoped', function () {
    $staff = scpStaff(['staff_id' => 52, 'dept_id' => 1, 'isadmin' => 1]);

    DB::connection('legacy')->table('ticket')->insert([
        ['ticket_id' => 1, 'number' => 'A', 'dept_id' => 1, 'staff_id' => 0],
        ['ticket_id' => 2, 'number' => 'B', 'dept_id' => 3, 'staff_id' => 0],
    ]);

    Auth::guard('staff')->login($staff);

    expect(Ticket::query()->pluck('ticket_id')->all())->toBe([1, 2]);
});

test('preferences page creates own preference row and logs access', function () {
    $staff = scpStaff(['staff_id' => 53]);

    $response = $this->actingAs($staff, 'staff')
        ->withHeaders(inertiaHeaders())
        ->get('/scp/preferences');

    $response->assertOk();
    $response->assertJsonPath('component', 'Scp/Preferences/Index');

    expect(DB::connection('osticket2')->table('staff_preferences')->where('staff_id', 53)->exists())->toBeTrue()
        ->and(DB::connection('osticket2')->table('access_log')->where('staff_id', 53)->where('action', 'scp.preferences.show')->exists())->toBeTrue();
});

test('inactive authenticated staff is denied scp access', function () {
    $staff = scpStaff(['staff_id' => 54, 'isactive' => 0]);

    $response = $this->actingAs($staff, 'staff')->get('/scp');

    $response->assertForbidden();
});
