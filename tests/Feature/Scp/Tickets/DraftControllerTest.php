<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Draft;
use App\Models\Staff;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_existing_draft(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $namespace = "ticket.note.{$ticket->ticket_id}";

        Draft::on('legacy')->create([
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
            'body' => 'Test draft body',
            'created' => now()->toDateTimeString(),
            'updated' => now()->toDateTimeString(),
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->getJson("/scp/tickets/{$ticket->ticket_id}/draft");

        $response->assertOk()
            ->assertJsonPath('body', 'Test draft body')
            ->assertJsonPath('updated', now()->toDateTimeString());
    }

    public function test_store_upserts_draft(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/draft", [
                'body' => 'New draft body',
            ]);

        $response->assertCreated()
            ->assertJsonPath('body', 'New draft body');

        $namespace = "ticket.note.{$ticket->ticket_id}";
        $this->assertDatabaseHas('draft', [
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
            'body' => 'New draft body',
        ], 'legacy');
    }

    public function test_update_upserts_draft(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $namespace = "ticket.note.{$ticket->ticket_id}";

        Draft::on('legacy')->create([
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
            'body' => 'Original body',
            'created' => now()->toDateTimeString(),
            'updated' => now()->toDateTimeString(),
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->patchJson("/scp/tickets/{$ticket->ticket_id}/draft", [
                'body' => 'Updated body',
            ]);

        $response->assertOk()
            ->assertJsonPath('body', 'Updated body');

        $this->assertDatabaseHas('draft', [
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
            'body' => 'Updated body',
        ], 'legacy');
    }

    public function test_destroy_removes_draft(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $namespace = "ticket.note.{$ticket->ticket_id}";

        Draft::on('legacy')->create([
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
            'body' => 'Draft to delete',
            'created' => now()->toDateTimeString(),
            'updated' => now()->toDateTimeString(),
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->deleteJson("/scp/tickets/{$ticket->ticket_id}/draft");

        $response->assertNoContent();

        $this->assertDatabaseMissing('draft', [
            'staff_id' => $staff->staff_id,
            'namespace' => $namespace,
        ], 'legacy');
    }
}
