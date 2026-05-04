<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Staff;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EnforceTicketLockMiddlewareTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route that uses the middleware
        Route::middleware(['auth:staff', 'scp.ticket-lock'])
            ->post('/__test/lock-probe/{ticket}', fn () => response()->noContent());

        // Ensure legacy config table exists
        if (! Schema::connection('legacy')->hasTable('config')) {
            Schema::connection('legacy')->create('config', function ($table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
            });
        }
    }

    public function test_passes_in_disabled_mode(): void
    {
        DB::connection('legacy')->table('config')->updateOrInsert(
            ['namespace' => 'core', 'key' => 'ticket_lock'],
            ['value' => '0']
        );

        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/__test/lock-probe/{$ticket->ticket_id}");

        $response->assertNoContent();
    }

    public function test_returns_423_in_on_view_mode_without_lock(): void
    {
        DB::connection('legacy')->table('config')->updateOrInsert(
            ['namespace' => 'core', 'key' => 'ticket_lock'],
            ['value' => '1']
        );

        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/__test/lock-probe/{$ticket->ticket_id}");

        $response->assertStatus(423);
        $response->assertJsonStructure(['message', 'held_by_staff_id', 'expires_at']);
    }

    public function test_passes_in_on_view_mode_with_owned_lock(): void
    {
        DB::connection('legacy')->table('config')->updateOrInsert(
            ['namespace' => 'core', 'key' => 'ticket_lock'],
            ['value' => '1']
        );

        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        // Create a lock owned by this staff member
        DB::connection('legacy')->table('lock')->insert([
            'object_type' => 'T',
            'object_id' => $ticket->ticket_id,
            'staff_id' => $staff->staff_id,
            'expire' => now()->addMinutes(5)->toDateTimeString(),
        ]);

        $response = $this->actingAs($staff, 'staff')
            ->postJson("/__test/lock-probe/{$ticket->ticket_id}");

        $response->assertNoContent();
    }
}
