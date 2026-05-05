<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Services\Scp\Tickets\NotePostingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NotePostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotePostingService $service;

    protected function setUp(): void
    {
        parent::setUp();

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

        foreach (['_search', 'thread_event', 'thread_entry', 'event', 'thread', 'ticket', 'staff'] as $table) {
            DB::connection('legacy')->table($table)->delete();
        }

        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created'],
        ]);

        $this->service = app(NotePostingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_posts_note_with_full_side_effect_chain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 11:15:00'));

        $ticket = Ticket::factory()->create([
            'lastupdate' => '2026-05-03 09:00:00',
            'updated' => '2026-05-03 09:00:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create([
            'object_type' => 'T',
            'lastresponse' => '2026-05-03 09:00:00',
        ]);
        $staff = Staff::factory()->create([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);
        $body = '<p>Internal <strong>note</strong> body</p>';

        $entry = $this->service->post(
            ticket: $ticket,
            thread: $thread,
            staff: $staff,
            body: $body,
            format: 'html',
            expectedUpdated: '2026-05-03 09:00:00',
        );

        $this->assertInstanceOf(ThreadEntry::class, $entry);

        $this->assertDatabaseHas('thread_entry', [
            'id' => $entry->id,
            'thread_id' => $thread->id,
            'staff_id' => $staff->staff_id,
            'type' => 'N',
            'format' => 'html',
            'body' => $body,
            'title' => '',
            'poster' => 'Ada Lovelace',
            'created' => '2026-05-03 11:15:00',
            'updated' => '2026-05-03 11:15:00',
        ], 'legacy');

        $this->assertDatabaseHas('thread_event', [
            'thread_id' => $thread->id,
            'event_id' => 7,
            'staff_id' => $staff->staff_id,
            'username' => 'Ada Lovelace',
            'uid' => $staff->staff_id,
            'uid_type' => 'S',
            'data' => json_encode(['entry_id' => $entry->id]),
        ], 'legacy');

        $this->assertDatabaseHas('_search', [
            'object_type' => 'THE',
            'object_id' => $entry->id,
            'title' => '',
            'content' => 'Internal note body',
        ], 'legacy');

        $ticket->refresh();
        $thread->refresh();

        $this->assertSame('2026-05-03 11:15:00', $ticket->lastupdate);
        $this->assertSame('2026-05-03 11:15:00', $ticket->updated);
        $this->assertSame('2026-05-03 11:15:00', $thread->lastresponse);
    }

    public function test_throws_409_on_concurrent_modification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-03 12:00:00'));

        $ticket = Ticket::factory()->create([
            'updated' => '2026-05-03 11:00:00',
            'lastupdate' => '2026-05-03 11:00:00',
        ]);
        $thread = Thread::factory()->for($ticket, 'ticket')->create([
            'object_type' => 'T',
        ]);
        $staff = Staff::factory()->create();

        try {
            $this->service->post(
                ticket: $ticket,
                thread: $thread,
                staff: $staff,
                body: 'Stale note',
                format: 'text',
                expectedUpdated: '2026-05-03 10:59:59',
            );

            $this->fail('Expected TicketModifiedConcurrentlyException was not thrown.');
        } catch (TicketModifiedConcurrentlyException $exception) {
            $this->assertSame($ticket->ticket_id, $exception->ticketId);
            $this->assertSame('2026-05-03 11:00:00', $exception->currentUpdated);
        }

        $this->assertDatabaseCount('thread_entry', 0, 'legacy');
        $this->assertDatabaseCount('thread_event', 0, 'legacy');
        $this->assertDatabaseCount('_search', 0, 'legacy');
    }

    private function ensureLegacyTable(string $table, \Closure $definition): void
    {
        if (! Schema::connection('legacy')->hasTable($table)) {
            Schema::connection('legacy')->create($table, $definition);
        }
    }
}
