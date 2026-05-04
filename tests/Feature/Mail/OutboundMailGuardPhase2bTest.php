<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\Event;
use App\Models\Queue;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class OutboundMailGuardPhase2bTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['osticket.ticket_lock' => '0']);

        // Seed legacy event table
        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
            ['id' => 100, 'name' => 'assigned', 'description' => 'Assigned'],
            ['id' => 101, 'name' => 'released', 'description' => 'Released'],
            ['id' => 200, 'name' => 'status', 'description' => 'Status Changed'],
        ]);

        // Seed legacy ticket_status table
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);

        // Create permissions
        Permission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        Permission::create(['name' => 'tickets.assign', 'guard_name' => 'staff']);
        Permission::create(['name' => 'tickets.set-status', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Clean up legacy tables
        DB::connection('legacy')->table('staff')->delete();
        DB::connection('legacy')->table('ticket')->delete();
        DB::connection('legacy')->table('thread')->delete();
        DB::connection('legacy')->table('thread_event')->delete();
        DB::connection('legacy')->table('thread_entry')->delete();
        DB::connection('legacy')->table('_search')->delete();
        DB::connection('legacy')->table('draft')->delete();
        DB::connection('legacy')->table('queue')->delete();
    }

    public function test_all_phase_2b_write_surfaces_send_no_mail(): void
    {
        // Create admin staff with all required permissions
        $staff = Staff::factory()->admin()->create();
        $staff->givePermissionTo('tickets.post-note');
        $staff->givePermissionTo('tickets.assign');
        $staff->givePermissionTo('tickets.set-status');

        // Create a second staff member for assignment
        $assignee = Staff::factory()->create();

        // Create ticket and threads
        $ticket = Ticket::factory()->create(['status_id' => 1, 'dept_id' => 1]);
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'A',
        ]);

        // Create queue for customization tests
        $queue = Queue::factory()->create();

        // 1. POST /scp/tickets/{ticket}/notes — post a note
        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/notes", [
                'body' => 'This is a test note',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
            ]);
        $this->assertContains($response->status(), [200, 302]);

        // Refresh ticket to get updated timestamp
        $ticket->refresh();

        // 2. POST /scp/tickets/{ticket}/assignment — assign to staff
        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'assignee_type' => 'staff',
                'assignee_id' => $assignee->staff_id,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);
        $this->assertContains($response->status(), [200, 302]);

        // Refresh ticket
        $ticket->refresh();

        // 3. DELETE /scp/tickets/{ticket}/assignment — unassign
        $response = $this->actingAs($staff, 'staff')
            ->deleteJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);
        $this->assertContains($response->status(), [200, 302, 204]);

        // Refresh ticket
        $ticket->refresh();

        // 4. POST /scp/tickets/{ticket}/status — change status to onhold
        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/status", [
                'status_id' => 3,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);
        $this->assertContains($response->status(), [200, 302]);

        // 5. POST /scp/tickets/{ticket}/draft — save a draft
        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/draft", [
                'body' => 'Draft note content',
            ]);
        $this->assertContains($response->status(), [200, 201]);

        // Assert no mail was sent
        Mail::assertNothingSent();
    }
}
