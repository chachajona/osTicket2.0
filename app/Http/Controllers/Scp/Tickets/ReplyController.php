<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyMentionedStaffJob;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\DraftService;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReplyController extends Controller
{
    public function __construct(
        private readonly ReplyPostingService $replies,
        private readonly DraftService $drafts,
        private readonly ActionLogger $audit,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        $this->authorize('postReply', $ticket);

        if ((string) config('mail.event_class_owner.reply') !== 'laravel') {
            abort(403, 'Reply mail is owned by legacy.');
        }

        /** @var Staff $staff */
        $staff = $request->user('staff');

        $data = $request->validate([
            'body' => 'required|string|max:65535',
            'format' => 'required|in:html,text',
            'signature' => ['required', Rule::in(['none', 'mine', 'dept'])],
            'reply_status_id' => 'nullable|integer|exists:legacy.ticket_status,id',
            'expected_updated' => 'required|string',
            'mentioned_staff_ids' => 'nullable|array',
            'mentioned_staff_ids.*' => 'integer',
        ]);

        $this->ensureSignatureAvailable($staff, $ticket, (string) $data['signature']);

        $thread = $ticket->thread()->firstOrFail();

        try {
            $entry = $this->replies->post(
                ticket: $ticket,
                thread: $thread,
                staff: $staff,
                body: (string) $data['body'],
                format: (string) $data['format'],
                signatureChoice: (string) $data['signature'],
                replyStatusId: isset($data['reply_status_id']) ? (int) $data['reply_status_id'] : null,
                expectedUpdated: (string) $data['expected_updated'],
            );
        } catch (TicketModifiedConcurrentlyException) {
            $this->audit->recordFailure($staff, 'reply.posted', 409, $ticket->ticket_id, TicketModifiedConcurrentlyException::class, $request);

            return response()->json(['message' => 'Ticket was modified'], 409);
        } catch (ForbiddenStatusTransition) {
            $this->audit->recordFailure($staff, 'reply.posted', 422, $ticket->ticket_id, ForbiddenStatusTransition::class, $request);

            return response()->json(['message' => 'Forbidden status transition'], 422);
        }

        foreach ((array) ($data['mentioned_staff_ids'] ?? []) as $mentionedId) {
            NotifyMentionedStaffJob::dispatch($ticket, $entry, $staff, (int) $mentionedId);
        }

        $this->drafts->discard($staff, "ticket.reply.{$ticket->ticket_id}");
        $this->audit->record(
            staff: $staff,
            action: 'reply.posted',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            request: $request,
        );

        return back()->with('success', 'Reply sent.');
    }

    private function ensureSignatureAvailable(Staff $staff, Ticket $ticket, string $choice): void
    {
        if ($choice === 'mine' && trim((string) ($staff->signature ?? '')) === '') {
            abort(response()->json([
                'message' => 'Signature not configured for staff member.',
                'errors' => ['signature' => ['Signature not configured for staff member.']],
            ], 422));
        }

        if ($choice === 'dept' && trim((string) ($ticket->department?->signature ?? '')) === '') {
            abort(response()->json([
                'message' => 'Department signature unavailable.',
                'errors' => ['signature' => ['Department signature unavailable.']],
            ], 422));
        }
    }
}
