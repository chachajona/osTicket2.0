<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use App\Services\Scp\Tickets\NotePostingService;
use App\Services\Scp\Tickets\StatusTransitionService;
use App\Services\Scp\Tickets\ThreadEventWriter;
use App\Services\Scp\Tickets\TicketCacheUpdater;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ThreadEventWriter $threadEvents;

    private NotePostingService $notes;

    private TicketCacheUpdater $cacheUpdater;

    private StatusTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLegacyTable('ticket_status', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('state');
            $table->string('mode')->nullable();
            $table->string('flags')->nullable();
            $table->integer('sort')->default(0);
            $table->json('properties')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
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

        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);

        DB::connection('legacy')->table('event')->insertOrIgnore([
            'id' => 200,
            'name' => 'status',
            'description' => 'Ticket status changed',
        ]);

        $this->threadEvents = app(ThreadEventWriter::class);
        $this->notes = app(NotePostingService::class);
        $this->cacheUpdater = app(TicketCacheUpdater::class);

        $this->service = new StatusTransitionService(
            $this->threadEvents,
            $this->notes,
            $this->cacheUpdater,
        );
    }

    public function test_open_to_onhold_allowed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 09:05:00'));

        $ticket = Ticket::factory()->create([
            'status_id' => 1,
            'lastupdate' => '2026-05-03 09:00:00',
            'updated' => '2026-05-03 09:00:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        $staff = Staff::factory()->create();

        $this->service->transition(
            ticket: $ticket,
            thread: $thread,
            caller: $staff,
            targetStatusId: 3,
            comments: null,
            expectedUpdated: '2026-05-03 09:00:00',
        );

        $ticket->refresh();
        $thread->refresh();

        $this->assertSame(3, (int) $ticket->status_id);
        $this->assertSame('2026-05-03 09:05:00', $ticket->updated);
        $this->assertSame('2026-05-03 09:05:00', $ticket->lastupdate);
        $this->assertSame('2026-05-03 09:05:00', $thread->lastresponse);

        $this->assertDatabaseHas('thread_event', [
            'thread_id' => $thread->id,
            'event_id' => 200,
            'staff_id' => $staff->staff_id,
            'data' => json_encode([
                'from' => ['id' => 1, 'name' => 'Open', 'state' => 'open'],
                'to' => ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
            ]),
        ], 'legacy');
    }

    public function test_onhold_to_open_allowed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 09:35:00'));

        $ticket = Ticket::factory()->create([
            'status_id' => 3,
            'lastupdate' => '2026-05-03 09:30:00',
            'updated' => '2026-05-03 09:30:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        $staff = Staff::factory()->create();

        $this->service->transition(
            ticket: $ticket,
            thread: $thread,
            caller: $staff,
            targetStatusId: 1,
            comments: null,
            expectedUpdated: '2026-05-03 09:30:00',
        );

        $ticket->refresh();
        $thread->refresh();

        $this->assertSame(1, (int) $ticket->status_id);
        $this->assertSame('2026-05-03 09:35:00', $ticket->updated);
        $this->assertSame('2026-05-03 09:35:00', $thread->lastresponse);

        $this->assertDatabaseHas('thread_event', [
            'thread_id' => $thread->id,
            'event_id' => 200,
            'staff_id' => $staff->staff_id,
            'data' => json_encode([
                'from' => ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
                'to' => ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ]),
        ], 'legacy');
    }

    public function test_closed_to_open_forbidden(): void
    {
        $ticket = Ticket::factory()->create([
            'status_id' => 2,
            'updated' => '2026-05-03 10:00:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        $staff = Staff::factory()->create();

        try {
            $this->service->transition(
                ticket: $ticket,
                thread: $thread,
                caller: $staff,
                targetStatusId: 1,
                comments: null,
                expectedUpdated: '2026-05-03 10:00:00',
            );

            $this->fail('Expected ForbiddenStatusTransition was not thrown.');
        } catch (ForbiddenStatusTransition $exception) {
            $this->assertSame('closed', $exception->fromState);
            $this->assertSame('open', $exception->toState);
        }

        $ticket->refresh();

        $this->assertSame(2, (int) $ticket->status_id);
        $this->assertDatabaseMissing('thread_event', [
            'thread_id' => $thread->id,
            'event_id' => 200,
            'staff_id' => $staff->staff_id,
        ], 'legacy');
    }

    public function test_open_to_closed_forbidden(): void
    {
        $ticket = Ticket::factory()->create([
            'status_id' => 1,
            'updated' => '2026-05-03 10:15:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        $staff = Staff::factory()->create();

        try {
            $this->service->transition(
                ticket: $ticket,
                thread: $thread,
                caller: $staff,
                targetStatusId: 2,
                comments: null,
                expectedUpdated: '2026-05-03 10:15:00',
            );

            $this->fail('Expected ForbiddenStatusTransition was not thrown.');
        } catch (ForbiddenStatusTransition $exception) {
            $this->assertSame('open', $exception->fromState);
            $this->assertSame('closed', $exception->toState);
        }

        $ticket->refresh();

        $this->assertSame(1, (int) $ticket->status_id);
        $this->assertDatabaseMissing('thread_event', [
            'thread_id' => $thread->id,
            'event_id' => 200,
            'staff_id' => $staff->staff_id,
        ], 'legacy');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ensureLegacyTable(string $table, \Closure $definition): void
    {
        if (! Schema::connection('legacy')->hasTable($table)) {
            Schema::connection('legacy')->create($table, $definition);
        }
    }
}
