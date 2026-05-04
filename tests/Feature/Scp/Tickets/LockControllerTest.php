<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Staff;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LockControllerTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacyConfigTable();
    }

    private function createLegacyConfigTable(): void
    {
        if (! Schema::connection('legacy')->hasTable('config')) {
            Schema::connection('legacy')->create('config', function ($table) {
                $table->id();
                $table->string('namespace')->default('core');
                $table->string('key');
                $table->text('value')->nullable();
                $table->unique(['namespace', 'key']);
            });
        }

        DB::connection('legacy')->table('config')->truncate();
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'ticket_lock', 'value' => '0'],
            ['namespace' => 'core', 'key' => 'lock_time', 'value' => '3'],
        ]);
    }

    public function test_acquire_returns_200_with_lock_payload(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock");

        $response->assertOk();
        $response->assertJsonStructure([
            'lock_id',
            'held_by_staff_id',
            'expires_at',
        ]);
    }

    public function test_acquire_returns_423_when_held_by_other(): void
    {
        $staff1 = Staff::factory()->admin()->create();
        $staff2 = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($staff1, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock")
            ->assertOk();

        $response = $this->actingAs($staff2, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock");

        $response->assertStatus(423);
        $response->assertJsonStructure([
            'held_by_staff_id',
            'expires_at',
        ]);
    }

    public function test_renew_returns_200_for_owner(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock")
            ->assertOk();

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock/renew");

        $response->assertOk();
        $response->assertJsonStructure([
            'lock_id',
            'held_by_staff_id',
            'expires_at',
        ]);
    }

    public function test_release_returns_204(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/lock")
            ->assertOk();

        $response = $this->actingAs($staff, 'staff')
            ->deleteJson("/scp/tickets/{$ticket->ticket_id}/lock");

        $response->assertNoContent();
    }

    public function test_guest_returns_404(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->postJson("/scp/tickets/{$ticket->ticket_id}/lock");

        // TicketAccessibleScope hides all tickets from unauthenticated users,
        // so route model binding returns 404 before auth middleware runs.
        $response->assertStatus(404);
    }
}
