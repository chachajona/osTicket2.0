<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Models\Ticket;
use App\Models\Thread;
use App\Services\Scp\Tickets\TicketCacheUpdater;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TicketCacheUpdaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_touches_ticket_lastupdate_and_thread_lastresponse(): void
    {
        // Set a fixed test time
        $testTime = Carbon::parse('2026-05-03 14:30:00');
        Carbon::setTestNow($testTime);

        // Create a ticket and thread
        $ticket = Ticket::factory()->create();
        $thread = Thread::factory()->for($ticket, 'ticket')->create();

        // Move time forward to ensure timestamps change
        Carbon::setTestNow($testTime->addMinutes(5));
        $newTime = Carbon::now();

        // Create the service and call touch()
        $updater = new TicketCacheUpdater();
        $updater->touch($ticket, $thread);

        // Refresh models from database
        $ticket->refresh();
        $thread->refresh();

        // Assert ticket timestamps were updated to new time
        $this->assertEquals($newTime->toDateTimeString(), $ticket->lastupdate);
        $this->assertEquals($newTime->toDateTimeString(), $ticket->updated);

        // Assert thread lastresponse was updated to new time
        $this->assertNotNull($thread->lastresponse);
        $this->assertEquals($newTime->toDateTimeString(), $thread->lastresponse);

        Carbon::setTestNow();
    }

    public function test_touches_ticket_without_thread(): void
    {
        // Set a fixed test time
        $testTime = Carbon::parse('2026-05-03 15:45:00');
        Carbon::setTestNow($testTime);

        // Create a ticket without a thread
        $ticket = Ticket::factory()->create();

        // Move time forward to ensure timestamps change
        Carbon::setTestNow($testTime->addMinutes(5));
        $newTime = Carbon::now();

        // Create the service and call touch() without thread
        $updater = new TicketCacheUpdater();
        $updater->touch($ticket);

        // Refresh model from database
        $ticket->refresh();

        // Assert ticket timestamps were updated to new time
        $this->assertEquals($newTime->toDateTimeString(), $ticket->lastupdate);
        $this->assertEquals($newTime->toDateTimeString(), $ticket->updated);

        Carbon::setTestNow();
    }
}
