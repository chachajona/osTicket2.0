<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\DraftService;
use App\Services\Scp\Tickets\NotePostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NoteController extends Controller
{
    public function __construct(
        private readonly NotePostingService $notes,
        private readonly DraftService $drafts,
        private readonly ActionLogger $audit,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $staff = $request->user('staff');
        $this->authorize('postNote', $ticket);
        $validated = $request->validate([
            'body' => 'required|string|max:65535',
            'format' => 'required|in:html,text',
            'expected_updated' => 'required|string',
        ]);
        $thread = $ticket->thread()->firstOrFail();
        $this->notes->post($ticket, $thread, $staff, $validated['body'], $validated['format'], $validated['expected_updated']);
        $this->drafts->discard($staff, "ticket.note.{$ticket->ticket_id}");
        $this->audit->record(
            staff: $staff,
            action: 'note.posted',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            request: $request,
        );
        return back()->with('success', 'Note posted.');
    }
}
