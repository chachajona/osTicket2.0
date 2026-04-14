<?php

use App\Console\Commands\FetchMailCommand;
use App\Models\EmailAccount;
use App\Models\EmailModel;
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

function ensureFetchMailLegacyTables(): void
{
    $schema = Schema::connection('legacy');

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
            $table->dateTime('lastupdate')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
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
    $command = new FetchMailCommand();
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

    return $command;
}
