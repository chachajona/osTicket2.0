<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\EmailAccount;
use App\Models\File;
use App\Models\FileChunk;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\ThreadEntryEmail;
use App\Models\Ticket;
use App\Services\EmailParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

final class FetchMailCommand extends Command
{
    protected $signature = 'tickets:fetch-mail {--dry-run}';

    protected $description = 'Fetch emails from configured mailboxes and create tickets';

    public function handle(EmailParser $parser): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY-RUN] No changes will be persisted.');
        }

        $accounts = EmailAccount::where('active', 1)
            ->where('type', 'mailbox')
            ->whereNotNull('host')
            ->where('host', '!=', '')
            ->with('email')
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active fetching email accounts configured.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($accounts as $account) {
            try {
                $processed = $this->processAccount($account, $parser, $dryRun);
                $total += $processed;
                $this->info("Account {$account->email->email}: {$processed} message(s) processed.");
            } catch (\Throwable $e) {
                $this->error("Account {$account->id} failed: {$e->getMessage()}");
                Log::error('FetchMailCommand: account error', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Total messages processed: {$total}.");

        return self::SUCCESS;
    }

    private function processAccount(
        EmailAccount $account,
        EmailParser $parser,
        bool $dryRun
    ): int {
        $client = $this->buildClient($account);
        $client->connect();

        try {
            $folder = $client->getFolder($account->folder ?: 'INBOX');
            $maxFetch = max(1, (int) ($account->fetchmax ?: 30));

            $messages = $folder->query()->unseen()->limit($maxFetch)->get();

            $processed = 0;
            foreach ($messages as $message) {
                try {
                    $this->processMessage($message, $account, $parser, $dryRun);
                    $processed++;

                    if (! $dryRun) {
                        $message->setFlag('Seen');

                        if ($account->postfetch === 'delete') {
                            $message->delete();
                        } elseif ($account->archivefolder) {
                            $message->move($account->archivefolder);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn("  Skipped message UID {$message->getUid()}: {$e->getMessage()}");
                    Log::warning('FetchMailCommand: message error', [
                        'uid' => $message->getUid(),
                        'account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! $dryRun) {
                $account->last_activity = now()->format('Y-m-d H:i:s');
                $account->save();
            }
        } finally {
            $client->disconnect();
        }

        return $processed;
    }

    private function processMessage(
        Message $message,
        EmailAccount $account,
        EmailParser $parser,
        bool $dryRun
    ): void {
        if ($parser->detectBounce($message)) {
            $this->line('  [bounce] Skipped bounce message.');

            return;
        }

        $headers = $parser->parseHeaders($message);

        $fromEmail = trim($headers['from_email']);
        if ($fromEmail === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->warn("  [invalid] Message UID {$message->getUid()} has missing/invalid From address; skipped.");

            return;
        }

        if (! empty($headers['message_id'])
            && ThreadEntryEmail::where('mid', $headers['message_id'])->exists()) {
            $this->line("  [duplicate] Already processed Message-ID {$headers['message_id']}");

            return;
        }

        $body = $parser->parseBody($message);
        $attachments = $parser->parseAttachments($message);

        $replyToIds = $parser->extractReplyToMessageIds($headers);
        $thread = $this->findExistingThread($replyToIds);

        if ($dryRun) {
            $mode = $thread ? 'reply to thread #'.$thread->id : 'new ticket';
            $this->line("  [dry-run] Would create {$mode}: \"{$headers['subject']}\" from {$headers['from_email']}");

            return;
        }

        if ($thread !== null) {
            $this->appendToThread($thread, $account, $headers, $body, $attachments);
        } else {
            $this->createTicket($account, $headers, $body, $attachments);
        }
    }

    private function findExistingThread(array $messageIds): ?Thread
    {
        if (empty($messageIds)) {
            return null;
        }

        $entry = ThreadEntryEmail::whereIn('mid', $messageIds)->first();

        return $entry?->threadEntry?->thread ?? null;
    }

    private function createTicket(
        EmailAccount $account,
        array $headers,
        array $body,
        array $attachments
    ): void {
        DB::connection('legacy')->transaction(function () use ($account, $headers, $body, $attachments) {
            $number = $this->nextTicketNumber();

            $userId = $this->resolveOrCreateUser($headers['from_email'], $headers['from_name']);

            $ticket = Ticket::create([
                'number' => $number,
                'user_id' => $userId,
                'dept_id' => $this->defaultDeptId($account),
                'status_id' => 1,
                'email_id' => $account->email_id,
                'source' => 'Email',
                'ip_address' => '',
                'created' => now()->format('Y-m-d H:i:s'),
                'updated' => now()->format('Y-m-d H:i:s'),
            ]);

            $thread = Thread::create([
                'object_id' => $ticket->ticket_id,
                'object_type' => 'T',
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            $entry = ThreadEntry::create([
                'thread_id' => $thread->id,
                'staff_id' => 0,
                'user_id' => $userId,
                'type' => 'M',
                'poster' => $headers['from_name'],
                'source' => 'Email',
                'title' => $headers['subject'],
                'body' => $body['body'],
                'format' => $body['format'],
                'created' => $headers['date'],
                'updated' => now()->format('Y-m-d H:i:s'),
            ]);

            ThreadEntryEmail::create([
                'thread_entry_id' => $entry->id,
                'email_id' => $account->email_id,
                'mid' => $headers['message_id'],
                'headers' => $this->buildRawHeaders($headers),
            ]);

            $this->saveAttachments($attachments, $entry->id);

            $this->line("  Created ticket #{$number} (id {$ticket->ticket_id}) from {$headers['from_email']}");
        });
    }

    private function appendToThread(
        Thread $thread,
        EmailAccount $account,
        array $headers,
        array $body,
        array $attachments
    ): void {
        DB::connection('legacy')->transaction(function () use ($thread, $account, $headers, $body, $attachments) {
            $userId = $this->resolveOrCreateUser($headers['from_email'], $headers['from_name']);

            $entry = ThreadEntry::create([
                'thread_id' => $thread->id,
                'staff_id' => 0,
                'user_id' => $userId,
                'type' => 'M',
                'poster' => $headers['from_name'],
                'source' => 'Email',
                'title' => $headers['subject'],
                'body' => $body['body'],
                'format' => $body['format'],
                'created' => $headers['date'],
                'updated' => now()->format('Y-m-d H:i:s'),
            ]);

            ThreadEntryEmail::create([
                'thread_entry_id' => $entry->id,
                'email_id' => $account->email_id,
                'mid' => $headers['message_id'],
                'headers' => $this->buildRawHeaders($headers),
            ]);

            $this->saveAttachments($attachments, $entry->id);

            Ticket::where('ticket_id', $thread->object_id)
                ->update(['lastupdate' => now()->format('Y-m-d H:i:s')]);

            $this->line("  Appended reply to thread #{$thread->id}");
        });
    }

    private function saveAttachments(array $attachments, int $threadEntryId): void
    {
        foreach ($attachments as $att) {
            try {
                $hash = md5($att['content']);

                $file = File::firstOrCreate(
                    ['key' => $hash],
                    [
                        'type' => $att['type'],
                        'size' => $att['size'],
                        'name' => $att['name'],
                        'bk' => 'D',
                        'ft' => 'P',
                        'signature' => sha1($att['content']),
                        'created' => now()->format('Y-m-d H:i:s'),
                    ]
                );

                if ($file->wasRecentlyCreated) {
                    FileChunk::create([
                        'file_id' => $file->id,
                        'chunk_id' => 0,
                        'filedata' => $att['content'],
                    ]);
                }

                Attachment::create([
                    'file_id' => $file->id,
                    'object_type' => 'H',
                    'object_id' => $threadEntryId,
                    'name' => $att['name'],
                    'inline' => $att['inline'] ? 1 : 0,
                ]);
            } catch (\Throwable $e) {
                Log::warning('FetchMailCommand: failed to save attachment', [
                    'name' => $att['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function nextTicketNumber(): string
    {
        return DB::connection('legacy')->transaction(function () {
            $seq = DB::connection('legacy')
                ->table('sequence')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $next = (int) $seq->next;
            $pad = (int) $seq->padding;

            DB::connection('legacy')
                ->table('sequence')
                ->where('id', 1)
                ->update([
                    'next' => $next + (int) $seq->increment,
                    'updated' => now()->format('Y-m-d H:i:s'),
                ]);

            return $pad > 0 ? str_pad((string) $next, $pad, '0', STR_PAD_LEFT) : (string) $next;
        });
    }

    private function resolveOrCreateUser(string $email, string $name): int
    {
        $userEmail = DB::connection('legacy')
            ->table('user_email')
            ->where('address', $email)
            ->first();

        if ($userEmail) {
            return (int) $userEmail->user_id;
        }

        $userId = DB::connection('legacy')->table('user')->insertGetId([
            'org_id' => 0,
            'default_email_id' => 0,
            'status' => 0,
            'name' => $name ?: $email,
            'created' => now()->format('Y-m-d H:i:s'),
            'updated' => now()->format('Y-m-d H:i:s'),
        ]);

        $emailId = DB::connection('legacy')->table('user_email')->insertGetId([
            'user_id' => $userId,
            'flags' => 0,
            'address' => $email,
        ]);

        DB::connection('legacy')->table('user')
            ->where('id', $userId)
            ->update(['default_email_id' => $emailId]);

        return (int) $userId;
    }

    private function defaultDeptId(EmailAccount $account): int
    {
        return (int) ($account->email?->dept_id ?: 1);
    }

    private function buildRawHeaders(array $headers): string
    {
        return implode("\n", [
            'From: '.$headers['from_email'],
            'Subject: '.$headers['subject'],
            'Message-ID: '.$headers['message_id'],
            'In-Reply-To: '.($headers['in_reply_to'] ?? ''),
            'References: '.($headers['references'] ?? ''),
            'Date: '.$headers['date'],
        ]);
    }

    private function buildClient(EmailAccount $account): Client
    {
        $encryption = match (strtolower((string) $account->encryption)) {
            'ssl' => 'ssl',
            'tls' => 'tls',
            default => false,
        };

        $manager = new ClientManager([]);

        return $manager->make([
            'host' => $account->host,
            'port' => (int) $account->port,
            'encryption' => $encryption,
            'validate_cert' => false,
            'username' => $account->auth_id,
            'password' => $account->auth_bk,
            'protocol' => strtolower((string) ($account->protocol ?: 'imap')),
        ]);
    }
}
