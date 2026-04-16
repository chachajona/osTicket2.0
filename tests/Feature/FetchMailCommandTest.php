<?php

use App\Console\Commands\FetchMailCommand;
use App\Models\EmailAccount;
use App\Models\EmailModel;
use App\Models\Thread;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    ensureFetchMailLegacyTables();

    foreach ([
        'attachment',
        'file_chunk',
        'file',
        'thread_entry_email',
        'thread_entry',
        'thread',
        'ticket__cdata',
        'ticket',
        'sequence',
        'user_email',
        'user',
    ] as $table) {
        DB::connection('legacy')->table($table)->delete();
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

test('findExistingThread ignores task threads', function () {
    $threadId = DB::connection('legacy')->table('thread')->insertGetId([
        'object_id' => 41,
        'object_type' => 'A',
        'created' => '2026-04-14 10:00:00',
    ]);

    $entryId = DB::connection('legacy')->table('thread_entry')->insertGetId([
        'thread_id' => $threadId,
        'staff_id' => 0,
        'user_id' => 0,
        'type' => 'M',
        'poster' => 'Task Sender',
        'source' => 'Email',
        'title' => 'Task message',
        'body' => 'Body',
        'format' => 'text',
        'created' => '2026-04-14 10:00:00',
        'updated' => '2026-04-14 10:00:00',
    ]);

    DB::connection('legacy')->table('thread_entry_email')->insert([
        'thread_entry_id' => $entryId,
        'email_id' => 7,
        'mid' => '<task@example.test>',
        'headers' => 'Message-ID: <task@example.test>',
    ]);

    $thread = callFetchMailCommandPrivateMethod(
        makeFetchMailCommand(),
        'findExistingThread',
        [['<task@example.test>']]
    );

    expect($thread)->toBeNull();
});

test('findExistingThread returns matching ticket threads', function () {
    $threadId = DB::connection('legacy')->table('thread')->insertGetId([
        'object_id' => 42,
        'object_type' => 'T',
        'created' => '2026-04-14 10:00:00',
    ]);

    $entryId = DB::connection('legacy')->table('thread_entry')->insertGetId([
        'thread_id' => $threadId,
        'staff_id' => 0,
        'user_id' => 0,
        'type' => 'M',
        'poster' => 'Ticket Sender',
        'source' => 'Email',
        'title' => 'Ticket message',
        'body' => 'Body',
        'format' => 'text',
        'created' => '2026-04-14 10:00:00',
        'updated' => '2026-04-14 10:00:00',
    ]);

    DB::connection('legacy')->table('thread_entry_email')->insert([
        'thread_entry_id' => $entryId,
        'email_id' => 7,
        'mid' => '<ticket@example.test>',
        'headers' => 'Message-ID: <ticket@example.test>',
    ]);

    $thread = callFetchMailCommandPrivateMethod(
        makeFetchMailCommand(),
        'findExistingThread',
        [['<ticket@example.test>']]
    );

    expect($thread)->not->toBeNull();
    expect($thread->id)->toBe($threadId);
    expect($thread->object_type)->toBe('T');
});

test('createTicket initializes lastupdate for new email tickets', function () {
    Carbon::setTestNow('2026-04-14 12:34:56');

    DB::connection('legacy')->table('sequence')->insert([
        'id' => 1,
        'next' => 9001,
        'increment' => 1,
        'padding' => 0,
        'updated' => '2026-04-14 12:00:00',
    ]);

    $account = new EmailAccount([
        'email_id' => 7,
    ]);
    $account->setRelation('email', new EmailModel([
        'email_id' => 7,
        'dept_id' => 9,
    ]));

    callFetchMailCommandPrivateMethod(
        makeFetchMailCommand(),
        'createTicket',
        [
            $account,
            [
                'from_email' => 'sender@example.test',
                'from_name' => 'Sender',
                'subject' => 'New ticket subject',
                'message_id' => '<new-ticket@example.test>',
                'in_reply_to' => '',
                'references' => '',
                'date' => '2026-04-14 12:00:00',
            ],
            [
                'body' => 'Ticket body',
                'format' => 'text',
            ],
            [],
        ]
    );

    $ticket = DB::connection('legacy')->table('ticket')->first();
    $thread = DB::connection('legacy')->table('thread')->first();
    $email = DB::connection('legacy')->table('thread_entry_email')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->number)->toBe('9001');
    expect($ticket->dept_id)->toBe(9);
    expect($ticket->lastupdate)->toBe('2026-04-14 12:34:56');
    expect($ticket->created)->toBe('2026-04-14 12:34:56');
    expect($ticket->updated)->toBe('2026-04-14 12:34:56');
    expect($thread)->not->toBeNull();
    expect($thread->object_type)->toBe('T');
    expect($email)->not->toBeNull();
    expect($email->mid)->toBe('<new-ticket@example.test>');
});

test('appendToThread marks reopened tickets in command output', function () {
    Carbon::setTestNow('2026-04-14 13:00:00');

    DB::connection('legacy')->table('ticket')->insert([
        'ticket_id' => 55,
        'number' => '9002',
        'user_id' => 1,
        'dept_id' => 9,
        'status_id' => 2,
        'email_id' => 7,
        'source' => 'Email',
        'ip_address' => '',
        'isanswered' => 1,
        'closed' => '2026-04-13 09:00:00',
        'lastupdate' => '2026-04-13 09:00:00',
        'created' => '2026-04-13 08:00:00',
        'updated' => '2026-04-13 09:00:00',
    ]);

    $threadId = DB::connection('legacy')->table('thread')->insertGetId([
        'object_id' => 55,
        'object_type' => 'T',
        'created' => '2026-04-13 08:00:00',
    ]);

    $account = new EmailAccount([
        'email_id' => 7,
    ]);

    [$command, $buffer] = makeFetchMailCommandWithBuffer();

    callFetchMailCommandPrivateMethod(
        $command,
        'appendToThread',
        [
            new Thread([
                'id' => $threadId,
                'object_id' => 55,
                'object_type' => 'T',
            ]),
            $account,
            [
                'from_email' => 'sender@example.test',
                'from_name' => 'Sender',
                'subject' => 'Re: Existing ticket',
                'message_id' => '<reply@example.test>',
                'in_reply_to' => '<ticket@example.test>',
                'references' => '<ticket@example.test>',
                'date' => '2026-04-14 12:59:00',
            ],
            [
                'body' => 'Reply body',
                'format' => 'text',
            ],
            [],
        ]
    );

    $ticket = DB::connection('legacy')->table('ticket')->where('ticket_id', 55)->first();

    expect($ticket->status_id)->toBe(1);
    expect($ticket->closed)->toBeNull();
    expect($ticket->isanswered)->toBe(0);
    expect($buffer->fetch())->toContain("Appended reply to thread #{$threadId} (reopened)");
});

test('appendToThread marks open answered tickets as unanswered', function () {
    Carbon::setTestNow('2026-04-14 14:00:00');

    DB::connection('legacy')->table('ticket')->insert([
        'ticket_id' => 56,
        'number' => '9003',
        'user_id' => 1,
        'dept_id' => 9,
        'status_id' => 1,
        'email_id' => 7,
        'source' => 'Email',
        'ip_address' => '',
        'isanswered' => 1,
        'closed' => null,
        'lastupdate' => '2026-04-13 09:00:00',
        'created' => '2026-04-13 08:00:00',
        'updated' => '2026-04-13 09:00:00',
    ]);

    $threadId = DB::connection('legacy')->table('thread')->insertGetId([
        'object_id' => 56,
        'object_type' => 'T',
        'created' => '2026-04-13 08:00:00',
    ]);

    [$command, $buffer] = makeFetchMailCommandWithBuffer();

    callFetchMailCommandPrivateMethod(
        $command,
        'appendToThread',
        [
            new Thread([
                'id' => $threadId,
                'object_id' => 56,
                'object_type' => 'T',
            ]),
            new EmailAccount([
                'email_id' => 7,
            ]),
            [
                'from_email' => 'sender@example.test',
                'from_name' => 'Sender',
                'subject' => 'Re: Existing open ticket',
                'message_id' => '<reply-open@example.test>',
                'in_reply_to' => '<ticket-open@example.test>',
                'references' => '<ticket-open@example.test>',
                'date' => '2026-04-14 13:59:00',
            ],
            [
                'body' => 'Follow-up from the user',
                'format' => 'text',
            ],
            [],
        ]
    );

    $ticket = DB::connection('legacy')->table('ticket')->where('ticket_id', 56)->first();

    expect($ticket->status_id)->toBe(1);
    expect($ticket->closed)->toBeNull();
    expect($ticket->isanswered)->toBe(0);
    expect($ticket->lastupdate)->toBe('2026-04-14 14:00:00');
    expect($buffer->fetch())->toContain("Appended reply to thread #{$threadId}")
        ->not->toContain('(reopened)');
});

test('buildClient reads validate_cert from config', function () {
    config()->set('services.imap.validate_cert', true);

    $client = callFetchMailCommandPrivateMethod(
        makeFetchMailCommand(),
        'buildClient',
        [
            new EmailAccount([
                'host' => 'imap.example.test',
                'port' => 993,
                'encryption' => 'ssl',
                'auth_id' => 'mailbox@example.test',
                'auth_bk' => 'secret',
                'protocol' => 'imap',
            ]),
        ]
    );

    expect($client->validate_cert)->toBeTrue();
});

test('saveAttachments backfills the file chunk when the file row already exists', function () {
    DB::connection('legacy')->table('file')->insert([
        'id' => 77,
        'type' => 'text/plain',
        'size' => 11,
        'name' => 'note.txt',
        'key' => md5('hello world'),
        'bk' => 'D',
        'ft' => 'P',
        'signature' => sha1('hello world'),
        'created' => '2026-04-14 12:00:00',
    ]);

    callFetchMailCommandPrivateMethod(
        makeFetchMailCommand(),
        'saveAttachments',
        [[
            [
                'name' => 'note.txt',
                'type' => 'text/plain',
                'size' => 11,
                'content' => 'hello world',
                'inline' => false,
            ],
        ], 123]
    );

    $chunk = DB::connection('legacy')->table('file_chunk')->where('file_id', 77)->first();
    $attachment = DB::connection('legacy')->table('attachment')->where('object_id', 123)->first();

    expect($chunk)->not->toBeNull();
    expect($chunk->chunk_id)->toBe(0);
    expect($chunk->filedata)->toBe('hello world');
    expect($attachment)->not->toBeNull();
    expect($attachment->file_id)->toBe(77);
});

function ensureFetchMailLegacyTables(): void
{
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('file')) {
        $schema->create('file', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('name')->default('');
            $table->string('key')->unique();
            $table->char('bk', 1)->default('D');
            $table->char('ft', 1)->default('P');
            $table->string('signature')->nullable();
            $table->dateTime('created')->nullable();
        });
    }

    if (! $schema->hasTable('file_chunk')) {
        $schema->create('file_chunk', function (Blueprint $table) {
            $table->unsignedInteger('file_id');
            $table->unsignedInteger('chunk_id');
            $table->binary('filedata');
            $table->primary(['file_id', 'chunk_id']);
        });
    }

    if (! $schema->hasTable('attachment')) {
        $schema->create('attachment', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('file_id');
            $table->char('object_type', 1);
            $table->unsignedInteger('object_id');
            $table->string('name')->default('');
            $table->unsignedTinyInteger('inline')->default(0);
        });
    }

    if (! $schema->hasTable('thread')) {
        $schema->create('thread', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('object_id')->default(0);
            $table->char('object_type', 1);
            $table->dateTime('created')->nullable();
        });
    }

    if (! $schema->hasTable('thread_entry')) {
        $schema->create('thread_entry', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('thread_id');
            $table->unsignedInteger('staff_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->char('type', 1);
            $table->string('poster')->default('');
            $table->string('source')->default('');
            $table->string('title')->default('');
            $table->text('body')->nullable();
            $table->string('format')->default('text');
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
    }

    if (! $schema->hasTable('thread_entry_email')) {
        $schema->create('thread_entry_email', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('thread_entry_id');
            $table->unsignedInteger('email_id')->default(0);
            $table->string('mid');
            $table->text('headers')->nullable();
        });
    }

    if (! $schema->hasTable('ticket')) {
        $schema->create('ticket', function (Blueprint $table) {
            $table->increments('ticket_id');
            $table->string('number');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('dept_id')->default(0);
            $table->unsignedInteger('status_id')->default(0);
            $table->unsignedInteger('email_id')->default(0);
            $table->string('source')->default('');
            $table->string('ip_address')->default('');
            $table->unsignedInteger('isanswered')->default(0);
            $table->dateTime('closed')->nullable();
            $table->dateTime('lastupdate')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
    } else {
        if (! $schema->hasColumn('ticket', 'isanswered')) {
            $schema->table('ticket', function (Blueprint $table) {
                $table->unsignedInteger('isanswered')->default(0);
            });
        }

        if (! $schema->hasColumn('ticket', 'closed')) {
            $schema->table('ticket', function (Blueprint $table) {
                $table->dateTime('closed')->nullable();
            });
        }
    }

    if (! $schema->hasTable('ticket__cdata')) {
        $schema->create('ticket__cdata', function (Blueprint $table) {
            $table->unsignedInteger('ticket_id')->primary();
            $table->text('subject')->nullable();
        });
    }

    if (! $schema->hasTable('sequence')) {
        $schema->create('sequence', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('next');
            $table->unsignedInteger('increment')->default(1);
            $table->unsignedInteger('padding')->default(0);
            $table->dateTime('updated')->nullable();
        });
    }

    if (! $schema->hasTable('user')) {
        $schema->create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('org_id')->default(0);
            $table->unsignedInteger('default_email_id')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->string('name')->default('');
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
    }

    if (! $schema->hasTable('user_email')) {
        $schema->create('user_email', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('flags')->default(0);
            $table->string('address')->unique();
        });
    }
}

function callFetchMailCommandPrivateMethod(FetchMailCommand $command, string $method, array $arguments): mixed
{
    $reflection = new ReflectionMethod($command, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($command, $arguments);
}

function makeFetchMailCommand(): FetchMailCommand
{
    $command = new FetchMailCommand;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    return $command;
}

function makeFetchMailCommandWithBuffer(): array
{
    $command = new FetchMailCommand;
    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    return [$command, $buffer];
}
