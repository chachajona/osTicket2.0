<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\EmailAccount;
use App\Models\EmailModel;
use App\Models\File;
use App\Models\FileChunk;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\ThreadEntryEmail;
use App\Models\Ticket;
use App\Models\TicketCdata;
use App\Services\EmailParser;
use App\Support\LegacyEmailAccountCredentialsResolver;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
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
                $label = $account->email?->email ?? "id:{$account->id}";
                $this->info("Account {$label}: {$processed} message(s) processed.");
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
                    $wasProcessed = $this->processMessage($message, $account, $parser, $dryRun);

                    if (! $dryRun) {
                        $message->setFlag('Seen');

                        if ($wasProcessed) {
                            $processed++;

                            if ($account->postfetch === 'delete') {
                                $message->delete();
                            } elseif ($account->archivefolder) {
                                $message->move($account->archivefolder);
                            }
                        }
                    } elseif ($wasProcessed) {
                        $processed++;
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

    /**
     * @return bool Whether the message was actually processed (not skipped).
     */
    private function processMessage(
        Message $message,
        EmailAccount $account,
        EmailParser $parser,
        bool $dryRun
    ): bool {
        if ($parser->detectBounce($message)) {
            $this->line('  [bounce] Skipped bounce message.');

            return false;
        }

        $headers = $parser->parseHeaders($message);

        $fromEmail = trim($headers['from_email']);
        if ($fromEmail === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->warn("  [invalid] Message UID {$message->getUid()} has missing/invalid From address; skipped.");

            return false;
        }

        if ($this->isSystemAddress($fromEmail)) {
            $this->line("  [loop] Skipped message from system address {$fromEmail}");

            return false;
        }

        if (! empty($headers['message_id'])
            && ThreadEntryEmail::where('mid', $headers['message_id'])->exists()) {
            $this->line("  [duplicate] Already processed Message-ID {$headers['message_id']}");

            return false;
        }

        $body = $parser->parseBody($message);
        $attachments = $parser->parseAttachments($message);

        $replyToIds = $parser->extractReplyToMessageIds($headers);
        $thread = $this->findExistingThread($replyToIds);

        if ($dryRun) {
            $mode = $thread ? 'reply to thread #'.$thread->id : 'new ticket';
            $this->line("  [dry-run] Would create {$mode}: \"{$headers['subject']}\" from {$headers['from_email']}");

            return true;
        }

        if ($thread !== null) {
            $this->appendToThread($thread, $account, $headers, $body, $attachments);
        } else {
            $this->createTicket($account, $headers, $body, $attachments);
        }

        return true;
    }

    private function findExistingThread(array $messageIds): ?Thread
    {
        if (empty($messageIds)) {
            return null;
        }

        $entry = ThreadEntryEmail::whereIn('mid', $messageIds)->first();

        $thread = $entry?->threadEntry?->thread;

        return ($thread && $thread->object_type === 'T') ? $thread : null;
    }

    private function createTicket(
        EmailAccount $account,
        array $headers,
        array $body,
        array $attachments
    ): void {
        // Allocate the ticket number in its own short transaction BEFORE the
        // outer ticket-creation transaction begins. nextTicketNumber() takes
        // a lockForUpdate() on ost_sequence, and InnoDB holds row locks until
        // the outermost enclosing transaction commits - savepoints from
        // Laravel's nested transaction() do not release them. If the lock is
        // taken inside the createTicket transaction it is held for the full
        // duration of user resolution, ticket/thread/entry inserts, cdata
        // upsert, and attachment persistence (which writes file binaries),
        // serialising every concurrent fetch-mail worker on a single row.
        // Running it here releases the lock as soon as the sequence row is
        // updated, which is the same scoping legacy osTicket uses.
        $number = $this->nextTicketNumber();

        DB::connection('legacy')->transaction(function () use ($account, $number, $headers, $body, $attachments) {
            $userId = $this->resolveOrCreateUser($headers['from_email'], $headers['from_name']);
            $timestamp = now()->format('Y-m-d H:i:s');

            $ticket = Ticket::create([
                'number' => $number,
                'user_id' => $userId,
                'dept_id' => $this->defaultDeptId($account),
                'status_id' => 1,
                'email_id' => $account->email_id,
                'source' => 'Email',
                'ip_address' => '',
                'lastupdate' => $timestamp,
                'created' => $timestamp,
                'updated' => $timestamp,
            ]);

            TicketCdata::updateOrCreate(
                ['ticket_id' => $ticket->ticket_id],
                ['subject' => $headers['subject']]
            );

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

            $ticket = Ticket::withoutGlobalScopes()
                ->where('ticket_id', $thread->object_id)
                ->first();

            $updates = [
                'lastupdate' => now()->format('Y-m-d H:i:s'),
                'isanswered' => 0,
            ];

            if ($ticket && $ticket->closed !== null) {
                $updates['status_id'] = 1;
                $updates['closed'] = null;
            }

            Ticket::withoutGlobalScopes()
                ->where('ticket_id', $thread->object_id)
                ->update($updates);

            $reopened = array_key_exists('closed', $updates) ? ' (reopened)' : '';
            $this->line("  Appended reply to thread #{$thread->id}{$reopened}");
        });
    }

    private function saveAttachments(array $attachments, int $threadEntryId): void
    {
        $connection = DB::connection('legacy');

        foreach ($attachments as $att) {
            try {
                $connection->transaction(function () use ($att, $threadEntryId): void {
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

                    FileChunk::firstOrCreate(
                        ['file_id' => $file->id, 'chunk_id' => 0],
                        ['filedata' => $att['content']]
                    );

                    Attachment::create([
                        'file_id' => $file->id,
                        'object_type' => 'H',
                        'object_id' => $threadEntryId,
                        'name' => $att['name'],
                        'inline' => $att['inline'] ? 1 : 0,
                    ]);
                });
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
        $connection = DB::connection('legacy');

        $userEmail = $connection->table('user_email')
            ->where('address', $email)
            ->first();

        if ($userEmail) {
            return (int) $userEmail->user_id;
        }

        try {
            // Wrap the user + user_email + default_email_id update in a nested
            // transaction so they land as an atomic unit on the legacy
            // connection. This method is invoked from inside createTicket()'s
            // outer transaction, so Laravel translates this nested call into a
            // SAVEPOINT. When a concurrent fetch-mail worker wins the race on
            // user_email.address the unique-constraint exception rolls the
            // savepoint back cleanly, discarding the ost_user insert that
            // would otherwise commit as an orphan row with default_email_id=0
            // once the outer transaction eventually commits.
            return $connection->transaction(function () use ($connection, $email, $name): int {
                $userId = $connection->table('user')->insertGetId([
                    'org_id' => 0,
                    'default_email_id' => 0,
                    'status' => 0,
                    'name' => $name ?: $email,
                    'created' => now()->format('Y-m-d H:i:s'),
                    'updated' => now()->format('Y-m-d H:i:s'),
                ]);

                $emailId = $connection->table('user_email')->insertGetId([
                    'user_id' => $userId,
                    'flags' => 0,
                    'address' => $email,
                ]);

                $connection->table('user')
                    ->where('id', $userId)
                    ->update(['default_email_id' => $emailId]);

                return (int) $userId;
            });
        } catch (UniqueConstraintViolationException) {
            // Use a locking read so MySQL does not reuse the outer
            // transaction's consistent-read snapshot under REPEATABLE READ.
            $userEmail = $connection->table('user_email')
                ->where('address', $email)
                ->lockForUpdate()
                ->first();

            if ($userEmail) {
                return (int) $userEmail->user_id;
            }

            throw new \RuntimeException("Failed to resolve user for {$email} after unique constraint violation.");
        }
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

    private function isSystemAddress(string $email): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));

        return EmailModel::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->exists();
    }

    private function buildClient(EmailAccount $account): Client
    {
        $encryption = match (strtolower((string) $account->encryption)) {
            'ssl' => 'ssl',
            'tls' => 'tls',
            default => false,
        };
        $credentials = app(LegacyEmailAccountCredentialsResolver::class)->resolve($account);

        $manager = new ClientManager([]);

        return $manager->make([
            'host' => $account->host,
            'port' => (int) $account->port,
            'encryption' => $encryption,
            'validate_cert' => (bool) config('services.imap.validate_cert', false),
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'protocol' => strtolower((string) ($account->protocol ?: 'imap')),
        ]);
    }
}
