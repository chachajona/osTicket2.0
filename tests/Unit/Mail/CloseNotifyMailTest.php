<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\CloseNotifyMail;
use App\Mail\EventClassHeader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class CloseNotifyMailTest extends TestCase
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
        $this->assertTrue(is_subclass_of(CloseNotifyMail::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(CloseNotifyMail::class, ShouldQueueAfterCommit::class));
    }

    public function test_attaches_close_notify_event_class_marker(): void
    {
        $fixture = $this->seedMailTicket(entryType: 'N', entryBody: 'Closing the ticket.');

        $mail = new CloseNotifyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            comments: 'Closing the ticket.',
            messageId: '<L-1-1-x@x>',
            inReplyTo: null,
            references: '',
        );
        $mail->build();

        $message = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($message);
        }

        $this->assertSame(
            EventClassHeader::CLOSE_NOTIFY,
            $message->getHeaders()->get(EventClassHeader::NAME)?->getBodyAsString(),
        );
    }

    public function test_failed_logs_close_mail_failed_audit_entry(): void
    {
        $fixture = $this->seedMailTicket(entryType: 'N', entryBody: 'Closing the ticket.');

        $mail = new CloseNotifyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            comments: 'msg',
            messageId: '<m@x>',
            inReplyTo: null,
            references: '',
        );

        $mail->failed(new \RuntimeException('SMTP boom'));

        $this->assertDatabaseHas('action_log', [
            'staff_id' => $fixture['staff']->staff_id,
            'action' => 'close.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $fixture['ticket']->ticket_id,
        ], 'osticket2');
    }
}
