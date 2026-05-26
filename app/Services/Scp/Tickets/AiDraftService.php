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
        $provider = (string) config('ai.provider', 'anthropic');
        $config = config("ai.providers.{$provider}");

        if (! is_array($config)) {
            throw new \RuntimeException("Unsupported AI provider [{$provider}].");
        }

        return match ($provider) {
            'anthropic' => $this->streamAnthropic($ticket, $userPrompt, $config),
            'openai' => $this->streamOpenAi($ticket, $userPrompt, $config),
            'gemini' => $this->streamGemini($ticket, $userPrompt, $config),
            default => throw new \RuntimeException("Unsupported AI provider [{$provider}]."),
        };
    }

    /**
     * @param  array{api_key: string|null, base_url: string, model: string}  $config
     */
    private function streamAnthropic(Ticket $ticket, string $userPrompt, array $config): Generator
    {
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['stream' => true, 'timeout' => 60, 'connect_timeout' => 10])
            ->post($config['base_url'].'/messages', [
                'model' => $config['model'],
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

    /**
     * @param  array{api_key: string|null, base_url: string, model: string}  $config
     */
    private function streamOpenAi(Ticket $ticket, string $userPrompt, array $config): Generator
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['stream' => true, 'timeout' => 60, 'connect_timeout' => 10])
            ->post($config['base_url'].'/chat/completions', [
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt($ticket)],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'stream' => true,
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
                if (is_array($data) && is_string($data['choices'][0]['delta']['content'] ?? null)) {
                    yield $data['choices'][0]['delta']['content'];
                }
            }
        }
    }

    /**
     * @param  array{api_key: string|null, base_url: string, model: string}  $config
     */
    private function streamGemini(Ticket $ticket, string $userPrompt, array $config): Generator
    {
        $model = rawurlencode($config['model']);
        $apiKey = rawurlencode((string) $config['api_key']);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['stream' => true, 'timeout' => 60, 'connect_timeout' => 10])
            ->post($config['base_url']."/models/{$model}:streamGenerateContent?key={$apiKey}&alt=sse", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $this->buildSystemPrompt($ticket)."\n\n".$userPrompt],
                        ],
                    ],
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

                $data = json_decode(substr($line, 6), true);
                if (is_array($data) && is_string($data['candidates'][0]['content']['parts'][0]['text'] ?? null)) {
                    yield $data['candidates'][0]['content']['parts'][0]['text'];
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
