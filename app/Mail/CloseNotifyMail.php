<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Services\Scp\Mail\LegacyTemplateRenderer;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Throwable;

final class CloseNotifyMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 120, 300];

    private ?RenderedMail $rendered = null;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $staff,
        public readonly string $comments,
        public readonly string $messageId,
        public readonly ?string $inReplyTo,
        public readonly string $references,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) ($this->ticket->department?->email?->email ?? config('mail.from.address'));
        $fromName = $this->staff->displayName();

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($fromAddress, $fromName)],
            subject: $this->renderedMail()->subject,
        );
    }

    public function content(): Content
    {
        $rendered = $this->renderedMail();

        return new Content(
            htmlString: $rendered->bodyHtml,
            textString: $rendered->bodyText,
        );
    }

    public function build(): self
    {
        return $this->withSymfonyMessage(function (Email $message): void {
            $headers = $message->getHeaders();
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', trim($this->messageId, '<>'));

            if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
                $headers->remove('In-Reply-To');
                $headers->addIdHeader('In-Reply-To', trim($this->inReplyTo, '<>'));
            }

            if ($this->references !== '') {
                $headers->remove('References');
                $headers->addTextHeader('References', $this->references);
            }

            $headers->addTextHeader(EventClassHeader::NAME, EventClassHeader::CLOSE_NOTIFY);
        });
    }

    public function failed(Throwable $exception): void
    {
        app(ActionLogger::class)->record(
            staff: $this->staff,
            action: 'close.mail_failed',
            outcome: 'failed',
            httpStatus: 0,
            ticketId: $this->ticket->ticket_id,
            beforeState: [
                'error_class' => $exception::class,
                'entry_id' => $this->entry->id,
                'message_id' => $this->messageId,
            ],
        );
    }

    private function renderedMail(): RenderedMail
    {
        if ($this->rendered !== null) {
            return $this->rendered;
        }

        $this->rendered = app(LegacyTemplateRenderer::class)
            ->render('note.alert', $this->ticket, $this->entry, null, bodyOverride: $this->comments);

        return $this->rendered;
    }
}
