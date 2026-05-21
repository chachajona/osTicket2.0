<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Models\Ticket;
use Generator;
use Illuminate\Support\Facades\Http;

class AiDraftService
{
    private const MAX_THREAD_CHARS = 3000;

    public function stream(Ticket $ticket, string $userPrompt): Generator
    {
        $response = Http::withHeaders([
            'x-api-key' => config('ai.api_key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['stream' => true, 'timeout' => 60, 'connect_timeout' => 10])
            ->post(config('ai.base_url').'/messages', [
                'model' => config('ai.model'),
                'max_tokens' => 1024,
                'stream' => true,
                'system' => $this->buildSystemPrompt($ticket),
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "AI provider error {$response->status()}: {$response->body()}",
                $response->status()
            );
        }

        $body = $response->getBody();

        $lineBuffer = '';
        while (! $body->eof()) {
            $chunk = $body->read(256);
            $lineBuffer .= $chunk;

            while (($pos = strpos($lineBuffer, "\n")) !== false) {
                $line = substr($lineBuffer, 0, $pos);
                $lineBuffer = substr($lineBuffer, $pos + 1);
                $line = rtrim($line, "\r");

                if (! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $payload = substr($line, 6);
                if ($payload === '[DONE]') {
                    return;
                }

                $data = json_decode($payload, true);
                if (
                    is_array($data)
                    && ($data['type'] ?? '') === 'content_block_delta'
                    && ($data['delta']['type'] ?? '') === 'text_delta'
                ) {
                    yield (string) $data['delta']['text'];
                }
            }
        }
    }

    private function buildSystemPrompt(Ticket $ticket): string
    {
        $subject = $ticket->cdata?->subject ?? '(no subject)';
        $threadExcerpt = $this->buildThreadExcerpt($ticket);

        return <<<PROMPT
You are a helpful support agent assistant. Draft a clear, professional, plain-text reply to the customer based on the ticket context below. Output ONLY the reply body — no subject line, no greeting prefix instructions, no markdown fences.

TICKET SUBJECT: {$subject}

CONVERSATION HISTORY:
{$threadExcerpt}
PROMPT;
    }

    private function buildThreadExcerpt(Ticket $ticket): string
    {
        $thread = $ticket->thread;
        if ($thread === null) {
            return '(no thread)';
        }

        $entries = $thread->entries()
            ->whereIn('type', ['M', 'R'])
            ->latest('created')
            ->limit(5)
            ->get()
            ->reverse();

        $lines = [];
        foreach ($entries as $entry) {
            $role = $entry->type === 'M' ? 'Customer' : 'Agent';
            $body = strip_tags((string) $entry->body);
            $body = preg_replace('/\s+/', ' ', $body);
            $lines[] = "[{$role}]: ".mb_substr(trim($body), 0, 600);
        }

        $excerpt = implode("\n\n", $lines);

        return mb_substr($excerpt, 0, self::MAX_THREAD_CHARS);
    }
}
