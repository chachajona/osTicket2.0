<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

final class MessageIdGenerator
{
    public function next(Ticket $ticket, ThreadEntry $entry): string
    {
        return sprintf(
            '<L-%d-%d-%s@%s>',
            $ticket->ticket_id,
            $entry->id,
            bin2hex(random_bytes(8)),
            $this->host(),
        );
    }

    public function inReplyTo(Thread $thread): ?string
    {
        $row = DB::connection('legacy')->table('thread_entry')
            ->join('thread_entry_email', 'thread_entry.id', '=', 'thread_entry_email.thread_entry_id')
            ->where('thread_entry.thread_id', $thread->id)
            ->where('thread_entry.type', 'M')
            ->orderByDesc('thread_entry.created')
            ->orderByDesc('thread_entry.id')
            ->select('thread_entry_email.mid')
            ->first();

        return $row?->mid;
    }

    public function references(Thread $thread): string
    {
        $mids = DB::connection('legacy')->table('thread_entry')
            ->join('thread_entry_email', 'thread_entry.id', '=', 'thread_entry_email.thread_entry_id')
            ->where('thread_entry.thread_id', $thread->id)
            ->orderBy('thread_entry.created')
            ->orderBy('thread_entry.id')
            ->pluck('thread_entry_email.mid')
            ->all();

        return implode(' ', $mids);
    }

    private function host(): string
    {
        $address = (string) (config('mail.from.address') ?? 'osticket@localhost');
        $host = str_contains($address, '@') ? strrchr($address, '@') : false;

        return is_string($host) ? ltrim($host, '@') : 'osticket.local';
    }
}
