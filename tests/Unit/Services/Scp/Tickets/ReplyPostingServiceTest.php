<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Mail\StaffReplyMail;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class ReplyPostingServiceTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
            ['id' => 200, 'name' => 'status', 'description' => 'Status'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);
    }

    public function test_writes_thread_entry_email_info_and_queues_mail(): void
    {
        $fixture = $this->seedMailTicket();

        $entry = app(ReplyPostingService::class)->post(
            ticket: $fixture['ticket'],
            thread: $fixture['thread'],
            staff: $fixture['staff'],
            body: 'My reply',
            format: 'html',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $fixture['ticket']->updated,
        );

        $this->assertSame('R', $entry->type);
        $this->assertSame('My reply', $entry->body);
        $this->assertDatabaseHas('thread_entry', ['id' => $entry->id, 'channel' => ''], 'legacy');
        $this->assertDatabaseHas('thread_entry_email', ['thread_entry_id' => $entry->id], 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    public function test_concurrency_check_throws_and_rolls_back(): void
    {
        $fixture = $this->seedMailTicket();

        try {
            app(ReplyPostingService::class)->post(
                ticket: $fixture['ticket'],
                thread: $fixture['thread'],
                staff: $fixture['staff'],
                body: 'My reply',
                format: 'html',
                signatureChoice: 'none',
                replyStatusId: null,
                expectedUpdated: 'wrong-timestamp',
            );

            $this->fail('Expected TicketModifiedConcurrentlyException was not thrown.');
        } catch (TicketModifiedConcurrentlyException) {
            $this->assertDatabaseMissing('thread_entry', [
                'thread_id' => $fixture['thread']->id,
                'type' => 'R',
                'body' => 'My reply',
            ], 'legacy');
            Mail::assertNothingQueued();
        }
    }

    public function test_post_with_status_change_transitions_in_same_transaction(): void
    {
        $fixture = $this->seedMailTicket();

        app(ReplyPostingService::class)->post(
            ticket: $fixture['ticket'],
            thread: $fixture['thread'],
            staff: $fixture['staff'],
            body: 'Done',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: 3,
            expectedUpdated: (string) $fixture['ticket']->updated,
        );

        $this->assertDatabaseHas('ticket', [
            'ticket_id' => $fixture['ticket']->ticket_id,
            'status_id' => 3,
        ], 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    public function test_post_with_status_change_creates_missing_status_event(): void
    {
        DB::connection('legacy')->table('event')->where('name', 'status')->delete();
        $fixture = $this->seedMailTicket();

        app(ReplyPostingService::class)->post(
            ticket: $fixture['ticket'],
            thread: $fixture['thread'],
            staff: $fixture['staff'],
            body: 'Done',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: 3,
            expectedUpdated: (string) $fixture['ticket']->updated,
        );

        $eventId = DB::connection('legacy')->table('event')
            ->where('name', 'status')
            ->value('id');

        $this->assertNotNull($eventId);
        $this->assertDatabaseHas('thread_event', [
            'thread_id' => $fixture['thread']->id,
            'event_id' => $eventId,
        ], 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }
}
