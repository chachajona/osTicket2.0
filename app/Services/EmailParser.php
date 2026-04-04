<?php

declare(strict_types=1);

namespace App\Services;

use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * EmailParser service.
 *
 * Adapts webklex/php-imap Message objects into plain arrays
 * suitable for ticket creation. Mirrors logic from the legacy
 * osticket/include/class.mailparse.php file.
 */
final class EmailParser
{
    /**
     * Extract key headers from an IMAP message.
     *
     * Returns a normalised array with keys:
     *   from_name, from_email, subject, message_id,
     *   in_reply_to, references, date
     *
     * @return array<string, string|null>
     */
    public function parseHeaders(Message $message): array
    {
        $from = $message->getFrom();
        $fromEmail = null;
        $fromName = null;

        if ($from && $from->count() > 0) {
            $first = $from->first();
            $fromEmail = (string) ($first->mail ?? $first->mailbox ?? '');
            $fromName = (string) ($first->personal ?? $fromEmail);
        }

        // message_id comes back as an Attribute – cast to string safely
        $messageId = $this->attributeToString($message->getMessageId());
        $inReplyTo = $this->attributeToString($message->getInReplyTo());
        $references = $this->attributeToString($message->getReferences());
        $subject = $this->attributeToString($message->getSubject()) ?: '(No Subject)';

        $date = null;
        try {
            $dateAttr = $message->getDate();
            if ($dateAttr && $dateAttr->count() > 0) {
                $date = $dateAttr->first()?->format('Y-m-d H:i:s');
            }
        } catch (\Throwable) {
            $date = now()->format('Y-m-d H:i:s');
        }

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName ?: $fromEmail,
            'subject' => $subject,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
            'date' => $date ?? now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Extract HTML and/or plain-text body from a message.
     *
     * Prefers HTML if available; falls back to plain text.
     *
     * @return array{html: string, text: string, body: string, format: string}
     */
    public function parseBody(Message $message): array
    {
        $html = $message->hasHTMLBody() ? $message->getHTMLBody() : '';
        $text = $message->getTextBody();

        // Prefer HTML body for the stored entry; fall back to plain text
        if ($html !== '') {
            $body = $html;
            $format = 'html';
        } else {
            $body = $text;
            $format = 'text';
        }

        return [
            'html' => $html,
            'text' => $text,
            'body' => $body,
            'format' => $format,
        ];
    }

    /**
     * Extract attachments from a message.
     *
     * Returns an array of attachment descriptors:
     *   name, type, size, content, inline
     *
     * @return array<int, array{name: string, type: string, size: int, content: string, inline: bool}>
     */
    public function parseAttachments(Message $message): array
    {
        $result = [];

        if (! $message->hasAttachments()) {
            return $result;
        }

        foreach ($message->getAttachments() as $attachment) {
            /** @var Attachment $attachment */
            try {
                $content = $attachment->getContent();
                if ($content === null || $content === '') {
                    continue;
                }

                $name = (string) ($attachment->getName() ?? 'attachment');
                $type = $attachment->getMimeType() ?? 'application/octet-stream';
                $size = strlen($content);
                // Inline attachments are typically images used inside HTML body
                $inline = $attachment->getType() === 'inline';

                $result[] = [
                    'name' => $name,
                    'type' => $type,
                    'size' => $size,
                    'content' => $content,
                    'inline' => $inline,
                ];
            } catch (\Throwable) {
                // Skip corrupt / unreadable attachments silently
            }
        }

        return $result;
    }

    /**
     * Detect whether an email is a bounce / delivery-failure notice.
     *
     * Mirrors legacy class.mailparse.php::isBounceNotice().
     * Checks:
     *  1. Auto-Submitted header value "auto-replied" or "auto-generated"
     *  2. X-Auto-Response-Suppress header
     *  3. Content-Type multipart/report with report-type=delivery-status
     *  4. From address patterns common in bounce mail
     */
    public function detectBounce(Message $message): bool
    {
        try {
            $header = $message->getHeader();
            if ($header === null) {
                return false;
            }

            $raw = $header->raw ?? '';

            // Check Auto-Submitted header
            if (preg_match('/^Auto-Submitted:\s*(auto-replied|auto-generated)/im', $raw)) {
                return true;
            }

            // X-Auto-Response-Suppress indicates automated system responses
            if (preg_match('/^X-Auto-Response-Suppress:/im', $raw)) {
                return true;
            }

            // Content-Type: multipart/report; report-type=delivery-status
            if (preg_match('/Content-Type:\s*multipart\/report.*report-type=delivery-status/is', $raw)) {
                return true;
            }

            // Check From address for common bounce patterns (MAILER-DAEMON, postmaster)
            $from = $this->attributeToString($message->getFrom()?->first()?->mail ?? null);
            if ($from && preg_match('/^(mailer-daemon|postmaster|noreply|no-reply)@/i', $from)) {
                return true;
            }

            // Content-Type: message/delivery-status
            if (preg_match('/message\/delivery-status/i', $raw)) {
                return true;
            }
        } catch (\Throwable) {
            // If we can't parse, assume not a bounce
        }

        return false;
    }

    /**
     * Parse the In-Reply-To and References headers into a list of
     * Message-IDs that could match an existing thread entry.
     *
     * @param  array<string, string|null>  $headers  Result of parseHeaders()
     * @return string[]
     */
    public function extractReplyToMessageIds(array $headers): array
    {
        $ids = [];

        if (! empty($headers['in_reply_to'])) {
            // May contain multiple IDs separated by whitespace
            foreach (preg_split('/\s+/', trim($headers['in_reply_to'])) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        if (! empty($headers['references'])) {
            foreach (preg_split('/\s+/', trim($headers['references'])) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_unique(array_filter($ids));
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Safely coerce a webklex Attribute (or null/scalar) to string.
     */
    private function attributeToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        // Webklex Attribute objects implement __toString and first()
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
