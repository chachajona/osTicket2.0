<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Event;
use App\Models\LegacyPermission;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
