<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Jobs\NotifyMentionedStaffJob;
use App\Mail\StaffReplyMail;
use App\Models\LegacyPermission;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class ReplyControllerTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);
        Mail::fake();
        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
            ['id' => 200, 'name' => 'status', 'description' => 'Status'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);
        LegacyPermission::create(['name' => 'tickets.post-reply', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_post_replies_writes_thread_entry_and_queues_mail_when_owner_laravel(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.post-reply');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('thread_entry', [
            'thread_id' => $fixture['thread']->id,
            'type' => 'R',
            'channel' => '',
            'body' => 'My reply',
        ], 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    public function test_post_replies_returns_403_when_owner_legacy(): void
    {
        config(['mail.event_class_owner.reply' => 'legacy']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.post-reply');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertForbidden();

        Mail::assertNothingQueued();
    }

    public function test_returns_409_when_expected_updated_mismatches(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.post-reply');

        $this->actingAs($fixture['staff'], 'staff')
            ->postJson(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => 'stale-timestamp',
            ])
            ->assertStatus(409);
    }

    public function test_returns_422_when_signature_option_unavailable_to_staff(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        $fixture = $this->seedMailTicket(staffSignature: null);
        $fixture['staff']->givePermissionTo('tickets.post-reply');

        $this->actingAs($fixture['staff'], 'staff')
            ->postJson(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'mine',
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertStatus(422);
    }

    public function test_dispatches_mention_jobs_for_mentioned_staff_ids(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        Queue::fake();

        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.post-reply');
        /** @var Staff $mentioned */
        $mentioned = Staff::factory()->create(['isactive' => 1]);

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'Hey @Someone check this out',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $fixture['ticket']->updated,
                'mentioned_staff_ids' => [$mentioned->staff_id],
            ])
            ->assertRedirect();

        Queue::assertPushed(
            NotifyMentionedStaffJob::class,
            fn ($job) => $job->mentionedStaffId === $mentioned->staff_id
        );
    }

    public function test_no_mention_jobs_dispatched_when_mentioned_staff_ids_empty(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        Queue::fake();

        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.post-reply');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
                'body' => 'A reply with no mentions',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $fixture['ticket']->updated,
                'mentioned_staff_ids' => [],
            ])
            ->assertRedirect();

        Queue::assertNotPushed(NotifyMentionedStaffJob::class);
    }
}
