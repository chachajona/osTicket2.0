<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Jobs\NotifyMentionedStaffJob;
use App\Models\Event;
use App\Models\LegacyPermission;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);
    }

    public function test_returns_403_without_permission(): void
    {
        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'created',
        ]);

        $staff = Staff::factory()->create();

        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/notes", [
                'body' => 'This is a test note',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertForbidden();
    }

    public function test_returns_409_on_stale_token(): void
    {
        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'created',
        ]);

        $perm = LegacyPermission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        $staff = Staff::factory()->create();
        $staff->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/notes", [
                'body' => 'This is a test note',
                'format' => 'html',
                'expected_updated' => 'stale-token-value',
            ]);

        $response->assertStatus(409);
    }

    public function test_posts_note_returns_success(): void
    {
        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'created',
        ]);

        $perm = LegacyPermission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        $staff = Staff::factory()->create();
        $staff->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/notes", [
                'body' => 'This is a test note',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertStatus(302);
    }

    public function test_dispatches_mention_jobs_for_mentioned_staff_ids(): void
    {
        Queue::fake();

        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'created',
        ]);

        $perm = LegacyPermission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        /** @var Staff $staff */
        $staff = Staff::factory()->create();
        $staff->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ticket = Ticket::factory()->create(['updated' => now()->toDateTimeString()]);
        Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        /** @var Staff $mentioned */
        $mentioned = Staff::factory()->create(['isactive' => 1]);

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.notes.store', $ticket), [
                'body' => '<p>Hey @Someone look at this</p>',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
                'mentioned_staff_ids' => [$mentioned->staff_id],
            ])
            ->assertRedirect();

        Queue::assertPushed(
            NotifyMentionedStaffJob::class,
            fn ($job) => $job->mentionedStaffId === $mentioned->staff_id
        );
    }

    public function test_no_mention_jobs_when_mentioned_staff_ids_empty(): void
    {
        Queue::fake();

        Event::on('legacy')->create([
            'id' => 7,
            'name' => 'created',
        ]);

        $perm = LegacyPermission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        /** @var Staff $staff */
        $staff = Staff::factory()->create();
        $staff->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ticket = Ticket::factory()->create(['updated' => now()->toDateTimeString()]);
        Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.notes.store', $ticket), [
                'body' => '<p>A note with no mentions</p>',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
                'mentioned_staff_ids' => [],
            ])
            ->assertRedirect();

        Queue::assertNotPushed(NotifyMentionedStaffJob::class);
    }
}
