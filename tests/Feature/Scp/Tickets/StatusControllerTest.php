<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class StatusControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);

        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 200, 'name' => 'status', 'description' => 'Status Changed'],
        ]);

        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);

        Permission::create(['name' => 'tickets.set-status', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::connection('legacy')->table('staff')->delete();
        DB::connection('legacy')->table('ticket')->delete();
        DB::connection('legacy')->table('thread')->delete();
        DB::connection('legacy')->table('thread_event')->delete();
        DB::connection('legacy')->table('thread_entry')->delete();
        DB::connection('legacy')->table('_search')->delete();
    }

    public function test_transitions_to_onhold_succeeds(): void
    {
        $staff = Staff::factory()->admin()->create();
        $staff->givePermissionTo('tickets.set-status');

        $ticket = Ticket::factory()->create(['status_id' => 1, 'dept_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/status", [
                'status_id' => 3,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertFound();
        $this->assertDatabaseHas('ticket', [
            'ticket_id' => $ticket->ticket_id,
            'status_id' => 3,
        ], 'legacy');
    }

    public function test_forbidden_transition_returns_422(): void
    {
        $staff = Staff::factory()->admin()->create();
        $staff->givePermissionTo('tickets.set-status');

        $ticket = Ticket::factory()->create(['status_id' => 2, 'dept_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/status", [
                'status_id' => 1,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Forbidden status transition');
    }

    public function test_returns_403_without_permission(): void
    {
        $staff = Staff::factory()->create();

        $ticket = Ticket::factory()->create(['status_id' => 1, 'dept_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/status", [
                'status_id' => 3,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertForbidden();
    }

    public function test_returns_409_on_stale_token(): void
    {
        $staff = Staff::factory()->admin()->create();
        $staff->givePermissionTo('tickets.set-status');

        $ticket = Ticket::factory()->create(['status_id' => 1, 'dept_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/status", [
                'status_id' => 3,
                'comments' => null,
                'expected_updated' => '2020-01-01 00:00:00',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Ticket was modified');
    }
}
