<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MentionNotificationMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $mentioner,
        public readonly Staff $mentioned,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You were mentioned in ticket #{$this->ticket->ticket_id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.mention-notification',
        );
    }
}
