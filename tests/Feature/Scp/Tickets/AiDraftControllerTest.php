<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Staff;
use App\Models\Ticket;
use App\Services\Scp\Tickets\AiDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AiDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $ticket = Ticket::factory()->create();

        // TicketAccessibleScope adds whereRaw('1 = 0') for unauthenticated users,
        // so route model binding yields 404 before auth middleware returns 401.
        $this->postJson("/scp/tickets/{$ticket->ticket_id}/draft/ai", [
            'prompt' => 'Write a polite follow-up.',
        ])->assertNotFound();
    }

    public function test_validates_prompt_is_required(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/draft/ai", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_validates_prompt_max_length(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($staff, 'staff')
            ->postJson("/scp/tickets/{$ticket->ticket_id}/draft/ai", [
                'prompt' => str_repeat('a', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_streams_ai_draft_on_success(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->mock(AiDraftService::class, function ($mock) {
            $mock->shouldReceive('stream')
                ->once()
                ->withArgs(fn ($t, $p) => $p === 'Write a polite follow-up.')
                ->andReturnUsing(function () {
                    yield 'Hello, ';
                    yield 'here is your draft reply.';
                });
        });

        $response = $this->actingAs($staff, 'staff')
            ->post("/scp/tickets/{$ticket->ticket_id}/draft/ai", [
                'prompt' => 'Write a polite follow-up.',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('Hello, ', $body);
        $this->assertStringContainsString('here is your draft reply.', $body);
    }

    public function test_streams_error_event_on_quota_exceeded(): void
    {
        $staff = Staff::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->mock(AiDraftService::class, function ($mock) {
            $mock->shouldReceive('stream')
                ->once()
                ->withArgs(fn ($t, $p) => $p === 'Write a polite follow-up.')
                ->andThrow(new \RuntimeException('AI provider error 429: rate limited', 429));
        });

        $response = $this->actingAs($staff, 'staff')
            ->post("/scp/tickets/{$ticket->ticket_id}/draft/ai", [
                'prompt' => 'Write a polite follow-up.',
            ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('AI quota exceeded', $body);
    }
}
