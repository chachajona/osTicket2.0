<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Models\Staff;
use App\Models\Ticket;
use App\Services\Scp\Tickets\AiDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
                ->withArgs(fn ($ticketArgument, $p) => $ticketArgument instanceof Ticket && $p === 'Write a polite follow-up.')
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
                ->withArgs(fn ($ticketArgument, $p) => $ticketArgument instanceof Ticket && $p === 'Write a polite follow-up.')
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

    public function test_ai_draft_service_streams_anthropic_response(): void
    {
        config()->set('ai.provider', 'anthropic');
        config()->set('ai.providers.anthropic.api_key', 'anthropic-key');
        config()->set('ai.providers.anthropic.model', 'claude-test');

        Http::fake([
            'api.anthropic.com/*' => Http::response("data: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Hello\"}}\n\n"),
        ]);

        $draft = implode('', iterator_to_array(app(AiDraftService::class)->stream($this->ticketForAiDraftService(), 'Draft a reply.')));

        $this->assertSame('Hello', $draft);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'anthropic-key')
            && $request['model'] === 'claude-test'
            && $request['stream'] === true);
    }

    public function test_ai_draft_service_streams_openai_response(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.providers.openai.api_key', 'openai-key');
        config()->set('ai.providers.openai.model', 'gpt-test');

        Http::fake([
            'api.openai.com/*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\ndata: [DONE]\n"),
        ]);

        $draft = implode('', iterator_to_array(app(AiDraftService::class)->stream($this->ticketForAiDraftService(), 'Draft a reply.')));

        $this->assertSame('Hello', $draft);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer openai-key')
            && $request['model'] === 'gpt-test'
            && $request['stream'] === true);
    }

    public function test_ai_draft_service_streams_gemini_response(): void
    {
        config()->set('ai.provider', 'gemini');
        config()->set('ai.providers.gemini.api_key', 'gemini-key');
        config()->set('ai.providers.gemini.model', 'gemini-test');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response("data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Hello\"}]}}]}\n\n"),
        ]);

        $draft = implode('', iterator_to_array(app(AiDraftService::class)->stream($this->ticketForAiDraftService(), 'Draft a reply.')));

        $this->assertSame('Hello', $draft);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:streamGenerateContent?key=gemini-key&alt=sse'
            && $request['contents'][0]['role'] === 'user'
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Draft a reply.'));
    }

    private function ticketForAiDraftService(): Ticket
    {
        $ticket = new Ticket;
        $ticket->setRelation('cdata', (object) ['subject' => 'Test subject']);
        $ticket->setRelation('thread', null);

        return $ticket;
    }
}
