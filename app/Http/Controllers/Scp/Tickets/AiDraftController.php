<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\AiDraftService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AiDraftController extends Controller
{
    public function __construct(
        private readonly AiDraftService $aiDraft,
    ) {}

    public function store(Request $request, Ticket $ticket): StreamedResponse
    {
        $this->authorize('postReply', $ticket);

        $data = $request->validate([
            'prompt' => 'required|string|max:500',
        ]);

        return response()->stream(
            function () use ($ticket, $data): void {
                try {
                    foreach ($this->aiDraft->stream($ticket, $data['prompt']) as $text) {
                        echo 'data: '.json_encode(['text' => $text])."\n\n";
                        ob_flush();
                        flush();
                    }
                } catch (\RuntimeException $e) {
                    $status = $e->getCode() >= 400 ? $e->getCode() : 500;
                    $message = $status === 429
                        ? 'AI quota exceeded. Please try again later.'
                        : 'AI service error. Please try again.';
                    echo 'data: '.json_encode(['error' => $message])."\n\n";
                    ob_flush();
                    flush();
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}
