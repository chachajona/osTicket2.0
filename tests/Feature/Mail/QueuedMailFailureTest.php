<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\CloseNotifyMail;
use App\Mail\StaffReplyMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class QueuedMailFailureTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
    }

    public function test_staff_reply_mail_failed_logs_audit_entry(): void
    {
        $fixture = $this->seedMailTicket();

        (new StaffReplyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            signatureChoice: 'none',
            messageId: '<m@x>',
            inReplyTo: null,
            references: '',
        ))->failed(new \RuntimeException('SMTP exhausted'));

        $this->assertDatabaseHas('action_log', [
            'staff_id' => $fixture['staff']->staff_id,
            'action' => 'reply.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $fixture['ticket']->ticket_id,
        ], 'osticket2');
    }

    public function test_close_notify_mail_failed_logs_audit_entry(): void
    {
        $fixture = $this->seedMailTicket(entryType: 'N', entryBody: 'Closing');

        (new CloseNotifyMail(
            ticket: $fixture['ticket'],
            entry: $fixture['entry'],
            staff: $fixture['staff'],
            comments: 'msg',
            messageId: '<m@x>',
            inReplyTo: null,
            references: '',
        ))->failed(new \RuntimeException('SMTP exhausted'));

        $this->assertDatabaseHas('action_log', [
            'staff_id' => $fixture['staff']->staff_id,
            'action' => 'close.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $fixture['ticket']->ticket_id,
        ], 'osticket2');
    }
}
