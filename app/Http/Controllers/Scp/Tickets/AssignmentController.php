<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly ActionLogger $audit,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $staff = $request->user('staff');
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'assignee_type' => 'required|in:staff,team',
            'assignee_id' => 'required|integer|min:1',
            'comments' => 'nullable|string|max:65535',
            'expected_updated' => 'required|string',
        ]);

        $thread = $ticket->thread()->firstOrFail();

        $this->assignments->assign(
            $ticket,
            $thread,
            $staff,
            $validated['assignee_type'],
            $validated['assignee_id'],
            $validated['comments'] ?? null,
            $validated['expected_updated'],
        );

        $this->audit->record(
            staff: $staff,
            action: 'ticket.assigned',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            request: $request,
        );

        return back();
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $staff = $request->user('staff');
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'comments' => 'nullable|string|max:65535',
            'expected_updated' => 'required|string',
        ]);

        $thread = $ticket->thread()->firstOrFail();

        $this->assignments->release(
            $ticket,
            $thread,
            $staff,
            $validated['comments'] ?? null,
            $validated['expected_updated'],
        );

        $this->audit->record(
            staff: $staff,
            action: 'ticket.unassigned',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            request: $request,
        );

        return back();
    }
}
