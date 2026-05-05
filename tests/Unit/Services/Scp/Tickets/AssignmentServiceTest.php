<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Models\Staff;
use App\Models\Team;
use App\Models\Thread;
use App\Models\ThreadEvent;
use App\Models\Ticket;
use App\Services\Scp\Tickets\AssignmentService;
use App\Services\Scp\Tickets\NotePostingService;
use App\Services\Scp\Tickets\ThreadEventWriter;
use App\Services\Scp\Tickets\TicketCacheUpdater;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentService $service;

    private NotePostingService $notePostingService;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('legacy')->dropIfExists('ticket');
        Schema::connection('legacy')->create('ticket', function (Blueprint $table): void {
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

        $this->ensureLegacyTable('event', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name')->unique();
            $table->text('description')->nullable();
        });

        $this->ensureLegacyTable('thread_event', function (Blueprint $table): void {
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

        $this->ensureLegacyTable('_search', function (Blueprint $table): void {
            $table->string('object_type', 8);
            $table->unsignedInteger('object_id');
            $table->text('title');
            $table->text('content');
            $table->primary(['object_type', 'object_id']);
        });

        $this->ensureLegacyTable('thread_entry', function (Blueprint $table): void {
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

        $this->ensureLegacyTable('team', function (Blueprint $table): void {
            $table->unsignedInteger('team_id')->autoIncrement();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('flags')->default(1);
            $table->string('name', 64);
            $table->string('notes', 255)->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });

        DB::connection('legacy')->table('thread_event')->delete();
        DB::connection('legacy')->table('thread_entry')->delete();
        DB::connection('legacy')->table('_search')->delete();
        DB::connection('legacy')->table('event')->delete();
        DB::connection('legacy')->table('team')->delete();

        DB::connection('legacy')->table('event')->insert([
            ['id' => 7, 'name' => 'created', 'description' => 'Note created'],
            ['id' => 100, 'name' => 'assigned', 'description' => 'Ticket assigned'],
            ['id' => 101, 'name' => 'released', 'description' => 'Ticket released'],
        ]);

        $this->notePostingService = app(NotePostingService::class);

        $this->service = new AssignmentService(
            app(ThreadEventWriter::class),
            $this->notePostingService,
            app(TicketCacheUpdater::class),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_assigns_to_staff_clears_team(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 16:00:00'));

        $ticket = Ticket::factory()->create([
            'staff_id' => 0,
            'team_id' => 44,
            'updated' => '2026-05-03 15:00:00',
            'lastupdate' => '2026-05-03 15:00:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create([
            'object_type' => 'T',
        ]);
        $caller = Staff::factory()->create([
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
        ]);
        $assignee = Staff::factory()->create();

        $this->service->assign(
            ticket: $ticket,
            thread: $thread,
            caller: $caller,
            type: 'staff',
            assigneeId: $assignee->staff_id,
            comments: 'Taking ownership',
            expectedUpdated: '2026-05-03 15:00:00',
        );

        $ticket->refresh();

        $this->assertSame($assignee->staff_id, (int) $ticket->staff_id);
        $this->assertSame(0, (int) $ticket->team_id);
        $this->assertSame('2026-05-03 16:00:00', (string) $ticket->updated);
        $this->assertSame('2026-05-03 16:00:00', (string) $ticket->lastupdate);
        $this->assertDatabaseHas('thread_entry', [
            'thread_id' => $thread->id,
            'staff_id' => $caller->staff_id,
            'type' => 'N',
            'format' => 'text',
            'body' => 'Taking ownership',
        ], 'legacy');

        $event = ThreadEvent::on('legacy')->latest('id')->firstOrFail();
        $data = json_decode((string) $event->data, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(100, (int) $event->event_id);
        $this->assertSame($thread->id, (int) $event->thread_id);
        $this->assertSame($caller->staff_id, (int) $event->staff_id);
        $this->assertSame(['id' => $assignee->staff_id], $data['staff']);
        $this->assertNull($data['team']);
        $this->assertSame(['staff_id' => 0, 'team_id' => 44], $data['before']);
    }

    public function test_assigns_to_team_clears_staff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 17:00:00'));

        $assignedStaff = Staff::factory()->create();
        $caller = Staff::factory()->create();
        $team = Team::query()->create([
            'lead_id' => $caller->staff_id,
            'name' => 'Escalations',
            'notes' => 'Ops queue',
        ]);

        $ticket = Ticket::factory()->create([
            'staff_id' => $assignedStaff->staff_id,
            'team_id' => 0,
            'updated' => '2026-05-03 16:30:00',
            'lastupdate' => '2026-05-03 16:30:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create([
            'object_type' => 'T',
        ]);

        $this->service->assign(
            ticket: $ticket,
            thread: $thread,
            caller: $caller,
            type: 'team',
            assigneeId: $team->team_id,
            comments: null,
            expectedUpdated: '2026-05-03 16:30:00',
        );

        $ticket->refresh();

        $this->assertSame(0, (int) $ticket->staff_id);
        $this->assertSame($team->team_id, (int) $ticket->team_id);
        $this->assertSame('2026-05-03 17:00:00', (string) $ticket->updated);
        $this->assertDatabaseCount('thread_entry', 0, 'legacy');

        $event = ThreadEvent::on('legacy')->latest('id')->firstOrFail();
        $data = json_decode((string) $event->data, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(100, (int) $event->event_id);
        $this->assertNull($data['staff']);
        $this->assertSame(['id' => $team->team_id], $data['team']);
        $this->assertSame([
            'staff_id' => $assignedStaff->staff_id,
            'team_id' => 0,
        ], $data['before']);
    }

    public function test_release_zeros_both_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 18:00:00'));

        $caller = Staff::factory()->create([
            'firstname' => 'Katherine',
            'lastname' => 'Johnson',
        ]);
        $team = Team::query()->create([
            'lead_id' => $caller->staff_id,
            'name' => 'Tier 2',
        ]);
        $assignedStaff = Staff::factory()->create();

        $ticket = Ticket::factory()->create([
            'staff_id' => $assignedStaff->staff_id,
            'team_id' => $team->team_id,
            'updated' => '2026-05-03 17:30:00',
            'lastupdate' => '2026-05-03 17:30:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create([
            'object_type' => 'T',
        ]);

        $this->service->release(
            ticket: $ticket,
            thread: $thread,
            caller: $caller,
            comments: 'Released for reassignment',
            expectedUpdated: '2026-05-03 17:30:00',
        );

        $ticket->refresh();

        $this->assertSame(0, (int) $ticket->staff_id);
        $this->assertSame(0, (int) $ticket->team_id);
        $this->assertSame('2026-05-03 18:00:00', (string) $ticket->updated);
        $this->assertDatabaseHas('thread_entry', [
            'thread_id' => $thread->id,
            'staff_id' => $caller->staff_id,
            'type' => 'N',
            'format' => 'text',
            'body' => 'Released for reassignment',
        ], 'legacy');

        $event = ThreadEvent::on('legacy')->latest('id')->firstOrFail();
        $data = json_decode((string) $event->data, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(101, (int) $event->event_id);
        $this->assertNull($data['staff']);
        $this->assertNull($data['team']);
        $this->assertSame([
            'staff_id' => $assignedStaff->staff_id,
            'team_id' => $team->team_id,
        ], $data['before']);
    }

    private function ensureLegacyTable(string $table, \Closure $definition): void
    {
        if (! Schema::connection('legacy')->hasTable($table)) {
            Schema::connection('legacy')->create($table, $definition);
        }
    }
}
