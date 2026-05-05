<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEvent;
use App\Services\Scp\Tickets\ThreadEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class ThreadEventWriterTest extends TestCase
{
    use RefreshDatabase;

    private ThreadEventWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(ThreadEventWriter::class);

        // Create legacy event table if it doesn't exist
        if (! Schema::connection('legacy')->hasTable('event')) {
            Schema::connection('legacy')->create('event', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
            });
        }

        // Create legacy thread table if it doesn't exist
        if (! Schema::connection('legacy')->hasTable('thread')) {
            Schema::connection('legacy')->create('thread', function ($table) {
                $table->id();
                $table->unsignedInteger('object_id');
                $table->char('object_type', 1);
                $table->timestamp('created')->useCurrent();
            });
        }

        // Create legacy thread_event table if it doesn't exist
        if (! Schema::connection('legacy')->hasTable('thread_event')) {
            Schema::connection('legacy')->create('thread_event', function ($table) {
                $table->id();
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
                $table->timestamp('timestamp')->useCurrent();
            });
        }

        // Seed the event table with an 'assigned' event
        DB::connection('legacy')->table('event')->insertOrIgnore([
            'id' => 1,
            'name' => 'assigned',
            'description' => 'Ticket assigned',
        ]);
    }

    public function test_records_assigned_event_with_correct_event_id_thread_type_and_data_shape(): void
    {
        // Create test data
        $thread = Thread::factory()->create();
        $staff = Staff::factory()->create();

        // Record the event
        $result = $this->writer->record(
            thread: $thread,
            eventName: 'assigned',
            entryId: 123,
            staff: $staff,
            data: ['assigned_to' => $staff->staff_id]
        );

        // Assert the result is a ThreadEvent instance
        $this->assertInstanceOf(ThreadEvent::class, $result);

        // Assert the ThreadEvent was created with correct values
        $this->assertDatabaseHas('thread_event', [
            'thread_id' => $thread->id,
            'thread_type' => 'T',
            'event_id' => 1,
            'staff_id' => $staff->staff_id,
            'team_id' => 0,
            'dept_id' => 0,
            'topic_id' => 0,
            'uid' => $staff->staff_id,
            'uid_type' => 'S',
            'annulled' => 0,
        ], 'legacy');

        // Assert data is JSON encoded
        $this->assertDatabaseHas('thread_event', [
            'id' => $result->id,
            'data' => json_encode(['assigned_to' => $staff->staff_id]),
        ], 'legacy');

        // Assert username is set
        $this->assertNotNull($result->username);
    }

    public function test_records_task_thread_events_with_task_thread_type(): void
    {
        $thread = Thread::factory()->create(['object_type' => 'A']);
        $staff = Staff::factory()->create();

        $result = $this->writer->record(
            thread: $thread,
            eventName: 'assigned',
            entryId: null,
            staff: $staff,
        );

        $this->assertDatabaseHas('thread_event', [
            'id' => $result->id,
            'thread_id' => $thread->id,
            'thread_type' => 'A',
        ], 'legacy');
    }

    public function test_throws_for_unknown_event_name(): void
    {
        $thread = Thread::factory()->create();
        $staff = Staff::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown event name: unknown_event');

        $this->writer->record(
            thread: $thread,
            eventName: 'unknown_event',
            entryId: null,
            staff: $staff
        );
    }
}
