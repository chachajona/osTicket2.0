<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DraftController extends Controller
{
    public function __construct(
        private readonly DraftService $drafts,
    ) {}

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = "ticket.note.{$ticket->ticket_id}";

        $draft = $this->drafts->find($staff, $namespace);

        if ($draft === null) {
            return response()->json([
                'body' => '',
                'updated' => null,
            ]);
        }

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = "ticket.note.{$ticket->ticket_id}";

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $draft = $this->drafts->upsert($staff, $namespace, $validated['body']);

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ], 201);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = "ticket.note.{$ticket->ticket_id}";

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $draft = $this->drafts->upsert($staff, $namespace, $validated['body']);

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ]);
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = "ticket.note.{$ticket->ticket_id}";

        $this->drafts->discard($staff, $namespace);

        return response()->json(status: 204);
    }
}
