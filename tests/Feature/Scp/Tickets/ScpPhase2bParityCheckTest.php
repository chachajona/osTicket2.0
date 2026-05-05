<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Thread;
use App\Models\Ticket;
use Tests\TestCase;

final class ScpPhase2bParityCheckTest extends TestCase
{
    public function test_command_can_read_ticket_without_authenticated_staff(): void
    {
        $ticket = Ticket::factory()->create(['dept_id' => 1]);
        Thread::factory()->create([
            'object_id' => $ticket->ticket_id,
            'object_type' => 'T',
        ]);

        $this->artisan('scp:phase-2b-parity-check', ['--ticket' => $ticket->ticket_id])
            ->expectsOutput('Parity check complete. 1 tickets, 0 errors.')
            ->assertSuccessful();
    }
}
