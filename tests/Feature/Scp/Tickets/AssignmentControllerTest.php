<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Event;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AssignmentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);

        Permission::create(['name' => 'tickets.assign', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::connection('legacy')->table('staff')->truncate();
        DB::connection('legacy')->table('ticket')->truncate();
        DB::connection('legacy')->table('thread')->truncate();
        DB::connection('legacy')->table('event')->truncate();
    }

    private function seedEvents(): void
    {
        Event::on('legacy')->create(['id' => 100, 'name' => 'assigned']);
        Event::on('legacy')->create(['id' => 101, 'name' => 'released']);
    }

    public function test_assign_to_staff_succeeds(): void
    {
        $this->seedEvents();

        $caller = Staff::factory()->admin()->create();
        $caller->givePermissionTo('tickets.assign');

        $assignee = Staff::factory()->create();
        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($caller, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'assignee_type' => 'staff',
                'assignee_id' => $assignee->staff_id,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('ticket', [
            'ticket_id' => $ticket->ticket_id,
            'staff_id' => $assignee->staff_id,
            'team_id' => 0,
        ], 'legacy');
    }

    public function test_unassign_succeeds(): void
    {
        $this->seedEvents();

        $caller = Staff::factory()->admin()->create();
        $caller->givePermissionTo('tickets.assign');

        $ticket = Ticket::factory()->create(['staff_id' => 5]);
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($caller, 'staff')
            ->deleteJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('ticket', [
            'ticket_id' => $ticket->ticket_id,
            'staff_id' => 0,
            'team_id' => 0,
        ], 'legacy');
    }

    public function test_returns_403_without_permission(): void
    {
        $caller = Staff::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($caller, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'assignee_type' => 'staff',
                'assignee_id' => 1,
                'comments' => null,
                'expected_updated' => (string) $ticket->updated,
            ]);

        $response->assertStatus(403);
    }

    public function test_returns_409_on_stale_token(): void
    {
        $this->seedEvents();

        $caller = Staff::factory()->admin()->create();
        $caller->givePermissionTo('tickets.assign');

        $ticket = Ticket::factory()->create();
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $response = $this->actingAs($caller, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/assignment", [
                'assignee_type' => 'staff',
                'assignee_id' => 1,
                'comments' => null,
                'expected_updated' => 'stale-token',
            ]);

        $response->assertStatus(409);
    }
}
