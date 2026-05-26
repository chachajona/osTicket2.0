<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\EventClassHeader;
use App\Mail\StaffReplyMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class StaffReplyMailTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
    }

    public function test_implements_queue_after_commit(): void
    {
        $this->assertTrue(is_subclass_of(StaffReplyMail::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(StaffReplyMail::class, ShouldQueueAfterCommit::class));
    }

    public function test_attaches_event_class_marker_and_threading_headers(): void
    {
        $fixture = $this->seedMailTicket();

        $mail = new StaffReplyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            signatureChoice: 'none',
            messageId: '<L-1-1-abc@x>',
            inReplyTo: '<prev@x>',
            references: '<a@x> <prev@x>',
        );
        $mail->build();

        $message = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($message);
        }

        $headers = $message->getHeaders();

        $this->assertSame(EventClassHeader::REPLY, $headers->get(EventClassHeader::NAME)?->getBodyAsString());
        $this->assertSame('<L-1-1-abc@x>', $headers->get('Message-ID')?->getBodyAsString());
        $this->assertSame('<prev@x>', $headers->get('In-Reply-To')?->getBodyAsString());
        $this->assertSame('<a@x> <prev@x>', $headers->get('References')?->getBodyAsString());
    }

    public function test_failed_logs_reply_mail_failed_audit_entry(): void
    {
        $fixture = $this->seedMailTicket();

        $mail = new StaffReplyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            signatureChoice: 'none',
            messageId: '<m@x>',
            inReplyTo: null,
            references: '',
        );

        $mail->failed(new \RuntimeException('SMTP boom'));

        $this->assertDatabaseHas('action_log', [
            'staff_id' => $fixture['staff']->staff_id,
            'action' => 'reply.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $fixture['ticket']->ticket_id,
        ], 'osticket2');
    }
}
