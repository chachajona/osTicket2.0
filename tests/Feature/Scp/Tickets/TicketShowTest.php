<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Event;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\Ticket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class TicketShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection('legacy');

        $this->ensureLegacyTable($schema, 'department', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('tpl_id')->default(0);
            $table->unsignedInteger('sla_id')->default(0);
            $table->unsignedInteger('manager_id')->default(0);
            $table->unsignedInteger('email_id')->default(0);
            $table->unsignedInteger('dept_id')->default(0);
            $table->string('name')->default('');
            $table->text('signature')->nullable();
            $table->tinyInteger('ispublic')->default(1);
        });

        $this->ensureLegacyTable($schema, 'team', function (Blueprint $table): void {
            $table->unsignedInteger('team_id')->autoIncrement();
            $table->unsignedInteger('lead_id')->default(0);
            $table->unsignedInteger('flags')->default(0);
            $table->string('name')->default('');
            $table->text('notes')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });

        $this->ensureLegacyTable($schema, 'user', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('org_id')->default(0);
            $table->unsignedInteger('default_email_id')->default(0);
            $table->string('name')->default('');
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });

        $this->ensureLegacyTable($schema, 'user_email', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('flags')->default(0);
            $table->string('address')->default('');
        });

        $this->ensureLegacyTable($schema, 'ticket__cdata', function (Blueprint $table): void {
            $table->unsignedInteger('ticket_id')->primary();
            $table->string('subject')->nullable();
            $table->string('priority')->nullable();
        });

        $this->ensureLegacyTable($schema, 'form_field', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name')->nullable();
            $table->string('label')->nullable();
        });

        $this->ensureLegacyTable($schema, 'thread_collaborator', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('flags')->default(0);
            $table->unsignedInteger('thread_id');
            $table->unsignedInteger('user_id');
            $table->string('role')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });

        $this->ensureLegacyTable($schema, 'thread_referral', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('thread_id');
            $table->unsignedInteger('object_id');
            $table->string('object_type');
            $table->dateTime('created')->nullable();
        });

        $this->ensureLegacyTable($schema, 'file', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('bk', 8)->default('D');
            $table->string('type')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('name')->nullable();
            $table->string('key')->nullable();
            $table->string('signature')->nullable();
            $table->string('ft')->nullable();
            $table->string('mime')->nullable();
            $table->text('attrs')->nullable();
            $table->dateTime('created')->nullable();
        });

        $this->ensureLegacyTable($schema, 'file_chunk', function (Blueprint $table): void {
            $table->unsignedInteger('file_id');
            $table->unsignedInteger('chunk_id');
            $table->binary('filedata')->nullable();
            $table->primary(['file_id', 'chunk_id']);
        });

        $this->ensureLegacyTable($schema, 'attachment', function (Blueprint $table): void {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('file_id');
            $table->string('object_type');
            $table->unsignedInteger('object_id');
            $table->string('name')->nullable();
            $table->tinyInteger('inline')->default(0);
        });

        foreach (['attachment', 'file_chunk', 'file', 'thread_referral', 'thread_collaborator', 'form_field', 'ticket__cdata', 'user_email', 'user', 'team', 'department'] as $table) {
            DB::connection('legacy')->table($table)->delete();
        }
    }

    public function test_authenticated_staff_can_view_the_ticket_detail_page_with_expected_inertia_props(): void
    {
        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'assigned',
        ]);

        DB::connection('legacy')->table('department')->insert([
            'id' => 1,
            'name' => 'Support',
        ]);

        DB::connection('legacy')->table('user')->insert([
            'id' => 1,
            'default_email_id' => 1,
            'name' => 'Grace Hopper',
            'created' => now(),
            'updated' => now(),
        ]);

        DB::connection('legacy')->table('user_email')->insert([
            'id' => 1,
            'user_id' => 1,
            'address' => 'grace@example.com',
        ]);

        DB::connection('legacy')->table('form_field')->insert([
            ['id' => 1, 'name' => 'subject', 'label' => 'Subject'],
            ['id' => 2, 'name' => 'priority', 'label' => 'Priority'],
        ]);

        DB::connection('legacy')->table('ticket_status')->insert([
            'id' => 1,
            'name' => 'Open',
            'state' => 'open',
        ]);

        $staff = Staff::factory()->admin()->create([
            'dept_id' => 1,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
        ]);

        $ticket = Ticket::factory()->create([
            'user_id' => 1,
            'status_id' => 1,
            'dept_id' => 1,
            'staff_id' => $staff->staff_id,
            'sla_id' => 5,
            'created' => '2026-04-20 10:00:00',
            'updated' => '2026-04-20 10:30:00',
        ]);

        DB::connection('legacy')->table('ticket__cdata')->insert([
            'ticket_id' => $ticket->ticket_id,
            'subject' => 'Visible searchable ticket',
            'priority' => 'High',
        ]);

        $thread = Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
            'created' => '2026-04-20 10:00:00',
        ]);

        DB::connection('legacy')->table('thread_entry')->insert([
            [
                'id' => 400,
                'thread_id' => $thread->id,
                'staff_id' => $staff->staff_id,
                'user_id' => 0,
                'type' => 'M',
                'poster' => 'Ada Lovelace',
                'source' => 'web',
                'title' => 'Initial message',
                'body' => 'Initial message body',
                'format' => 'html',
                'created' => '2026-04-20 10:01:00',
                'updated' => '2026-04-20 10:01:00',
            ],
            [
                'id' => 401,
                'thread_id' => $thread->id,
                'staff_id' => $staff->staff_id,
                'user_id' => 0,
                'type' => 'N',
                'poster' => 'Ada Lovelace',
                'source' => 'web',
                'title' => 'Internal note',
                'body' => 'Internal note body',
                'format' => 'html',
                'created' => '2026-04-20 10:03:00',
                'updated' => '2026-04-20 10:03:00',
            ],
        ]);

        DB::connection('legacy')->table('thread_event')->insert([
            'id' => 500,
            'thread_id' => $thread->id,
            'thread_type' => 'T',
            'event_id' => 7,
            'staff_id' => $staff->staff_id,
            'team_id' => 0,
            'dept_id' => 1,
            'topic_id' => 0,
            'data' => json_encode(['to' => 'Ada Lovelace']),
            'username' => 'ada',
            'uid' => $staff->staff_id,
            'uid_type' => 'S',
            'annulled' => 0,
            'timestamp' => '2026-04-20 10:02:00',
        ]);

        DB::connection('legacy')->table('thread_collaborator')->insert([
            'id' => 600,
            'thread_id' => $thread->id,
            'user_id' => 1,
            'role' => 'cc',
            'created' => '2026-04-20 10:04:00',
            'updated' => '2026-04-20 10:04:00',
        ]);

        DB::connection('legacy')->table('thread_referral')->insert([
            'id' => 700,
            'thread_id' => $thread->id,
            'object_id' => 9,
            'object_type' => 'D',
            'created' => '2026-04-20 10:05:00',
        ]);

        DB::connection('legacy')->table('file')->insert([
            'id' => 800,
            'bk' => 'D',
            'size' => 11,
            'name' => 'hello.txt',
            'mime' => 'text/plain',
            'created' => '2026-04-20 10:02:30',
        ]);

        DB::connection('legacy')->table('attachment')->insert([
            'id' => 900,
            'file_id' => 800,
            'object_type' => 'H',
            'object_id' => 400,
            'name' => 'hello.txt',
            'inline' => 0,
        ]);

        $response = $this->actingAs($staff, 'staff')->get("/scp/tickets/{$ticket->ticket_id}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Scp/Tickets/Show')
            ->has('ticket', fn (AssertableInertia $ticketPage) => $ticketPage
                ->where('id', $ticket->ticket_id)
                ->where('number', (string) $ticket->number)
                ->where('status', 'Open')
                ->where('status_state', 'open')
                ->where('priority', 'High')
                ->where('department', 'Support')
                ->where('assignee', 'Ada Lovelace')
                ->where('sla_id', 5)
                ->where('subject', 'Visible searchable ticket')
                ->where('requester', 'Grace Hopper')
                ->where('requester_email', 'grace@example.com')
                ->etc()
            )
            ->has('customFields', fn (AssertableInertia $customFields) => $customFields
                ->where('Subject', 'Visible searchable ticket')
                ->where('Priority', 'High')
            )
            ->has('timeline', 3)
            ->has('timeline.0', fn (AssertableInertia $timelineItem) => $timelineItem
                ->where('kind', 'entry')
                ->where('id', 400)
                ->where('type', 'M')
                ->where('author', 'Ada Lovelace')
                ->where('body', 'Initial message body')
                ->where('format', 'html')
                ->where('created', '2026-04-20 10:01:00')
                ->etc()
            )
            ->has('timeline.1', fn (AssertableInertia $timelineItem) => $timelineItem
                ->where('kind', 'event')
                ->where('id', 500)
                ->where('event_id', 7)
                ->where('label', 'assigned')
                ->where('data', '{"to":"Ada Lovelace"}')
                ->where('created', '2026-04-20 10:02:00')
                ->etc()
            )
            ->has('timeline.2', fn (AssertableInertia $timelineItem) => $timelineItem
                ->where('kind', 'entry')
                ->where('id', 401)
                ->where('type', 'N')
                ->where('body', 'Internal note body')
                ->where('format', 'html')
                ->where('created', '2026-04-20 10:03:00')
                ->etc()
            )
            ->has('attachments', 1)
            ->has('attachments.0', fn (AssertableInertia $attachment) => $attachment
                ->where('id', 900)
                ->where('file_id', 800)
                ->where('name', 'hello.txt')
                ->where('mime', 'text/plain')
                ->where('size', 11)
                ->where('inline', false)
                ->where('download_url', route('scp.attachments.download', ['file' => 800]))
            )
            ->has('collaborators', 1)
            ->has('collaborators.0', fn (AssertableInertia $collaborator) => $collaborator
                ->where('id', 600)
                ->where('name', 'Grace Hopper')
                ->where('email', 'grace@example.com')
                ->where('role', 'cc')
            )
            ->has('referrals', 1)
            ->has('referrals.0', fn (AssertableInertia $referral) => $referral
                ->where('id', 700)
                ->where('object_type', 'D')
                ->where('object_id', 9)
                ->where('created', '2026-04-20 10:05:00')
            )
        );
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->withoutMiddleware(SubstituteBindings::class);

        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->get("/scp/tickets/{$ticket->ticket_id}");

        $response->assertRedirect('/scp/login');
    }

    private function ensureLegacyTable(mixed $schema, string $table, \Closure $definition): void
    {
        if (! $schema->hasTable($table)) {
            $schema->create($table, $definition);
        }
    }
}
