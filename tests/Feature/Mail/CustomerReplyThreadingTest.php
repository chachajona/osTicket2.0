<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\StaffReplyMail;
use App\Models\ThreadEntry;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class CustomerReplyThreadingTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['mail.from.address' => 'support@example.test']);
        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
            ['id' => 200, 'name' => 'status', 'description' => 'Status'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ]);
    }

    public function test_outbound_reply_persists_mid_in_thread_entry_email(): void
    {
        $fixture = $this->seedMailTicket();

        $entry = app(ReplyPostingService::class)->post(
            ticket: $fixture['ticket'],
            thread: $fixture['thread'],
            staff: $fixture['staff'],
            body: 'Reply',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $fixture['ticket']->updated,
        );

        $row = DB::connection('legacy')->table('thread_entry_email')->where('thread_entry_id', $entry->id)->first();

        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('/^<L-\d+-\d+-[a-f0-9]{16}@example\.test>$/', $row->mid);
    }

    public function test_in_reply_to_links_to_most_recent_customer_message(): void
    {
        $fixture = $this->seedMailTicket();
        $customerEntry = ThreadEntry::on('legacy')->create([
            'thread_id' => $fixture['thread']->id,
            'type' => 'M',
            'body' => 'Original',
            'poster' => 'Alice',
            'title' => '',
            'created' => '2026-01-01 10:00:00',
        ]);
        DB::connection('legacy')->table('thread_entry_email')->insert([
            'thread_entry_id' => $customerEntry->id,
            'mid' => '<customer-original@x>',
            'headers' => 'Message-ID: <customer-original@x>',
        ]);

        app(ReplyPostingService::class)->post(
            ticket: $fixture['ticket'],
            thread: $fixture['thread'],
            staff: $fixture['staff'],
            body: 'My reply',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $fixture['ticket']->updated,
        );

        Mail::assertQueued(StaffReplyMail::class, fn (StaffReplyMail $mail): bool => $mail->inReplyTo === '<customer-original@x>');
    }
}
