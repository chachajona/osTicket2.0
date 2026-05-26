<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Models\ThreadEntry;
use App\Services\Scp\Mail\MessageIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class MessageIdGeneratorTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.from.address' => 'support@example.test']);
        $this->ensureLegacyMailTables();
    }

    public function test_generates_message_id_in_documented_format(): void
    {
        $fixture = $this->seedMailTicket();

        $messageId = app(MessageIdGenerator::class)->next($fixture['ticket'], $fixture['entry']);

        $this->assertMatchesRegularExpression(
            '/^<L-'.$fixture['ticket']->ticket_id.'-'.$fixture['entry']->id.'-[a-f0-9]{16}@example\.test>$/',
            $messageId,
        );
    }

    public function test_in_reply_to_returns_most_recent_customer_message_mid(): void
    {
        $fixture = $this->seedMailTicket();
        $older = ThreadEntry::on('legacy')->create([
            'thread_id' => $fixture['thread']->id,
            'type' => 'M',
            'body' => 'older',
            'poster' => 'Alice',
            'title' => '',
            'created' => '2026-01-01 10:00:00',
        ]);
        $newer = ThreadEntry::on('legacy')->create([
            'thread_id' => $fixture['thread']->id,
            'type' => 'M',
            'body' => 'newer',
            'poster' => 'Alice',
            'title' => '',
            'created' => '2026-01-01 11:00:00',
        ]);

        DB::connection('legacy')->table('thread_entry_email')->insert([
            ['thread_entry_id' => $older->id, 'mid' => '<older@x>', 'headers' => ''],
            ['thread_entry_id' => $newer->id, 'mid' => '<newer@x>', 'headers' => ''],
        ]);

        $this->assertSame('<newer@x>', app(MessageIdGenerator::class)->inReplyTo($fixture['thread']));
    }

    public function test_references_walks_entries_in_created_order(): void
    {
        $fixture = $this->seedMailTicket();

        DB::connection('legacy')->table('thread_entry_email')->insert([
            ['thread_entry_id' => $fixture['entry']->id, 'mid' => '<reply@x>', 'headers' => ''],
        ]);

        $this->assertSame('<reply@x>', app(MessageIdGenerator::class)->references($fixture['thread']));
    }
}
