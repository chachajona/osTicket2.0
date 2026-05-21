<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LegacyMailFixtures
{
    protected function ensureLegacyMailTables(): void
    {
        if (! Schema::connection('legacy')->hasColumn('staff', 'signature')) {
            Schema::connection('legacy')->table('staff', function (Blueprint $table): void {
                $table->text('signature')->nullable();
                $table->string('default_signature_type', 16)->nullable();
            });
        }

        $this->ensureLegacyMailTable('department', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('pid')->default(0);
            $table->unsignedInteger('dept_id')->nullable();
            $table->unsignedInteger('tpl_id')->default(1);
            $table->unsignedInteger('sla_id')->default(0);
            $table->unsignedInteger('manager_id')->default(0);
            $table->unsignedInteger('email_id')->default(0);
            $table->string('name')->default('');
            $table->text('signature')->nullable();
            $table->tinyInteger('ispublic')->default(1);
            $table->unsignedInteger('flags')->default(0);
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
        $this->addMissingLegacyMailColumns('department', [
            'pid' => fn (Blueprint $table) => $table->unsignedInteger('pid')->default(0),
            'flags' => fn (Blueprint $table) => $table->unsignedInteger('flags')->default(0),
            'created' => fn (Blueprint $table) => $table->dateTime('created')->nullable(),
            'updated' => fn (Blueprint $table) => $table->dateTime('updated')->nullable(),
        ]);

        $this->ensureLegacyMailTable('email', function (Blueprint $table): void {
            $table->unsignedInteger('email_id')->autoIncrement();
            $table->unsignedInteger('noautoresp')->default(0);
            $table->unsignedInteger('priority_id')->default(0);
            $table->unsignedInteger('dept_id')->default(0);
            $table->unsignedInteger('topic_id')->default(0);
            $table->string('email', 255);
            $table->string('name', 255)->default('');
            $table->text('notes')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
        $this->addMissingLegacyMailColumns('email', [
            'noautoresp' => fn (Blueprint $table) => $table->unsignedInteger('noautoresp')->default(0),
            'priority_id' => fn (Blueprint $table) => $table->unsignedInteger('priority_id')->default(0),
            'topic_id' => fn (Blueprint $table) => $table->unsignedInteger('topic_id')->default(0),
            'created' => fn (Blueprint $table) => $table->dateTime('created')->nullable(),
            'updated' => fn (Blueprint $table) => $table->dateTime('updated')->nullable(),
        ]);

        $this->ensureLegacyMailTable('user', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('org_id')->default(0);
            $table->unsignedInteger('default_email_id')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->string('name', 255)->default('');
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
        $this->addMissingLegacyMailColumns('user', [
            'status' => fn (Blueprint $table) => $table->unsignedInteger('status')->default(0),
        ]);

        $this->ensureLegacyMailTable('user_email', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('flags')->default(0);
            $table->string('address', 255);
        });

        $this->ensureLegacyMailTable('email_template_group', function (Blueprint $table): void {
            $table->unsignedInteger('tpl_id')->primary();
            $table->tinyInteger('isactive')->default(1);
            $table->string('name', 255);
            $table->string('lang', 16)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
        $this->addMissingLegacyMailColumns('email_template_group', [
            'lang' => fn (Blueprint $table) => $table->string('lang', 16)->nullable(),
            'notes' => fn (Blueprint $table) => $table->text('notes')->nullable(),
            'created' => fn (Blueprint $table) => $table->dateTime('created')->nullable(),
            'updated' => fn (Blueprint $table) => $table->dateTime('updated')->nullable(),
        ]);

        $this->ensureLegacyMailTable('email_template', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('tpl_id');
            $table->string('code_name', 64);
            $table->string('subject', 255);
            $table->longText('body');
        });

        $this->ensureLegacyMailTable('thread_entry_email', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('thread_entry_id');
            $table->unsignedInteger('email_id')->nullable();
            $table->string('mid', 255);
            $table->text('headers')->nullable();
            $table->index('mid');
            $table->index('thread_entry_id');
        });

        $this->ensureLegacyMailTable('ticket__cdata', function (Blueprint $table): void {
            $table->unsignedInteger('ticket_id')->primary();
            $table->string('subject', 255)->nullable();
            $table->string('priority', 64)->nullable();
        });

        foreach ([
            'thread_entry_email',
            'email_template',
            'email_template_group',
            'ticket__cdata',
            'department',
            'email',
            'user_email',
            'user',
        ] as $table) {
            DB::connection('legacy')->table($table)->delete();
        }
    }

    protected function seedMailTemplates(): void
    {
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
            ['tpl_id' => 2, 'isactive' => 1, 'name' => 'Engineering'],
        ]);

        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            [
                'id' => 1,
                'tpl_id' => 1,
                'code_name' => 'ticket.reply',
                'subject' => 'Re: %{ticket.subject} [#%{ticket.number}]',
                'body' => '<p>Hi %{ticket.name},</p><p>%{response}</p>%{signature}',
            ],
            [
                'id' => 2,
                'tpl_id' => 1,
                'code_name' => 'note.alert',
                'subject' => 'Ticket #%{ticket.number} update',
                'body' => '<p>%{comments}</p>',
            ],
            [
                'id' => 3,
                'tpl_id' => 2,
                'code_name' => 'ticket.reply',
                'subject' => '[Eng] %{ticket.subject}',
                'body' => '<p>%{response}</p>',
            ],
        ]);
    }

    /**
     * @return array{ticket: Ticket, thread: Thread, staff: Staff, entry: ThreadEntry}
     */
    protected function seedMailTicket(
        int $tplId = 1,
        string $subject = 'Test subject',
        string $entryType = 'R',
        string $entryBody = 'The response body',
        ?string $staffSignature = '<p>--<br>Bob</p>',
        ?string $deptSignature = '<p>--<br>Support</p>',
    ): array {
        $emailId = DB::connection('legacy')->table('email')->insertGetId([
            'dept_id' => 1,
            'email' => 'support@example.test',
            'name' => 'Support',
        ]);

        DB::connection('legacy')->table('department')->insertOrIgnore([
            'id' => 1,
            'dept_id' => 0,
            'tpl_id' => $tplId,
            'email_id' => $emailId,
            'name' => 'Support',
            'signature' => $deptSignature,
        ]);

        $userEmailId = DB::connection('legacy')->table('user_email')->insertGetId([
            'address' => 'alice@example.com',
        ]);
        $userId = DB::connection('legacy')->table('user')->insertGetId([
            'name' => 'Alice',
            'default_email_id' => $userEmailId,
        ]);
        DB::connection('legacy')->table('user_email')->where('id', $userEmailId)->update(['user_id' => $userId]);

        $staff = Staff::factory()->create([
            'dept_id' => 1,
            'firstname' => 'Bob',
            'lastname' => 'Builder',
            'signature' => $staffSignature,
        ]);

        $ticket = Ticket::factory()->create([
            'user_id' => $userId,
            'dept_id' => 1,
            'email_id' => $emailId,
            'status_id' => 1,
        ]);
        DB::connection('legacy')->table('ticket__cdata')->insert([
            'ticket_id' => $ticket->ticket_id,
            'subject' => $subject,
            'priority' => 'Normal',
        ]);

        $thread = Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);

        $entry = ThreadEntry::on('legacy')->create([
            'thread_id' => $thread->id,
            'staff_id' => $staff->staff_id,
            'type' => $entryType,
            'body' => $entryBody,
            'format' => 'html',
            'poster' => $staff->displayName(),
            'title' => '',
            'created' => now(),
            'updated' => now(),
        ]);

        return [
            'ticket' => $ticket->refresh(),
            'thread' => $thread->refresh(),
            'staff' => $staff->refresh(),
            'entry' => $entry->refresh(),
        ];
    }

    private function ensureLegacyMailTable(string $table, \Closure $definition): void
    {
        if (! Schema::connection('legacy')->hasTable($table)) {
            Schema::connection('legacy')->create($table, $definition);
        }
    }

    /**
     * @param  array<string, \Closure>  $columns
     */
    private function addMissingLegacyMailColumns(string $table, array $columns): void
    {
        $schema = Schema::connection('legacy');
        $missingColumns = array_filter(
            $columns,
            fn (string $column): bool => ! $schema->hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missingColumns === []) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($missingColumns): void {
            foreach ($missingColumns as $definition) {
                $definition($table);
            }
        });
    }
}
