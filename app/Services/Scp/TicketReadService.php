<?php

namespace App\Services\Scp;

use App\Models\Attachment;
use App\Models\Staff;
use App\Models\ThreadCollaborator;
use App\Models\ThreadEvent;
use App\Models\ThreadReferral;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TicketReadService
{
    public function __construct(private readonly TicketCustomFields $customFields) {}

    /**
     * @return array<string, mixed>
     */
    public function read(Ticket $ticket): array
    {
        $ticket->loadMissing([
            'department',
            'staff',
            'status',
            'team',
            'user.defaultEmail',
            'cdata',
            'thread.entries.staff',
        ]);

        $thread = $ticket->thread;
        $entries = $thread?->entries ?? collect();
        $threadId = $thread !== null ? (int) $thread->id : null;

        return [
            'ticket' => [
                'id' => (int) $ticket->ticket_id,
                'number' => (string) $ticket->number,
                'status' => $ticket->status?->name ?? (string) $ticket->status_id,
                'status_state' => $ticket->status?->state,
                'priority' => $ticket->cdata?->priority,
                'dept_id' => (int) $ticket->dept_id,
                'department' => $ticket->department?->name ?? (string) $ticket->dept_id,
                'assignee' => $ticket->staff?->displayName(),
                'team' => $ticket->team?->name,
                'sla_id' => (int) $ticket->sla_id,
                'source' => $ticket->source,
                'source_extra' => $ticket->source_extra,
                'ip_address' => $ticket->ip_address,
                'isoverdue' => (bool) $ticket->isoverdue,
                'isanswered' => (bool) $ticket->isanswered,
                'duedate' => $ticket->duedate,
                'est_duedate' => $ticket->est_duedate,
                'reopened' => $ticket->reopened,
                'created' => $ticket->created,
                'updated' => $ticket->updated,
                'lastupdate' => $ticket->lastupdate,
                'lastmessage' => $ticket->lastmessage,
                'lastresponse' => $ticket->lastresponse,
                'closed' => $ticket->closed,
                'subject' => $ticket->cdata?->subject,
                'requester' => $ticket->user?->name,
                'requester_email' => $ticket->user?->defaultEmail?->address,
                'dept_signature_available' => trim((string) ($ticket->department?->signature ?? '')) !== '',
            ],
            'permissions' => $this->permissions($ticket),
            'availableStatuses' => $this->availableStatuses(),
            'customFields' => $this->customFields->forTicket($ticket),
            'timeline' => $this->timeline($ticket),
            'attachments' => $this->attachments((int) $ticket->ticket_id, $entries->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            'collaborators' => $this->collaborators($threadId),
            'referrals' => $this->referrals($threadId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Ticket $ticket): array
    {
        $thread = $ticket->thread;

        if (! $thread) {
            return [];
        }

        $entries = $thread->entries
            ->map(fn ($entry): array => [
                'kind' => 'entry',
                'id' => (int) $entry->id,
                'type' => $entry->type,
                'author' => $entry->staff?->displayName(),
                'body' => $entry->body,
                'format' => $entry->format,
                'created' => $entry->created,
            ]);

        try {
            $events = ThreadEvent::query()
                ->with(['event', 'staff'])
                ->where('thread_id', $thread->id)
                ->orderBy('timestamp')
                ->get()
                ->map(fn (ThreadEvent $event): array => [
                    'kind' => 'event',
                    'id' => (int) $event->id,
                    'event_id' => (int) $event->event_id,
                    'label' => $event->event?->name ?? $event->username,
                    'data' => $event->data,
                    'created' => $event->timestamp,
                ]);
        } catch (QueryException) {
            $events = collect();
        }

        return $entries
            ->concat($events)
            ->sortBy('created')
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $entryIds
     * @return array<int, array<string, mixed>>
     */
    private function attachments(int $ticketId, array $entryIds): array
    {
        try {
            return Attachment::query()
                ->with('file')
                ->where(function ($query) use ($ticketId, $entryIds): void {
                    $query->where(function ($query) use ($ticketId): void {
                        $query->where('object_type', 'T')->where('object_id', $ticketId);
                    });

                    if ($entryIds !== []) {
                        $query->orWhere(function ($query) use ($entryIds): void {
                            $query->whereIn('object_id', $entryIds)
                                ->whereIn('object_type', ['H', 'E', 'M', 'R', 'N']);
                        });
                    }
                })
                ->orderBy('id')
                ->get()
                ->map(fn (Attachment $attachment): array => [
                    'id' => (int) $attachment->id,
                    'file_id' => (int) $attachment->file_id,
                    'name' => $attachment->name ?: $attachment->file?->name,
                    'mime' => $attachment->file?->mime,
                    'size' => $attachment->file?->size,
                    'inline' => (bool) $attachment->inline,
                    'download_url' => route('scp.attachments.download', ['file' => $attachment->file_id]),
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collaborators(?int $threadId): array
    {
        if ($threadId === null) {
            return [];
        }

        try {
            return ThreadCollaborator::query()
                ->with('user.defaultEmail')
                ->where('thread_id', $threadId)
                ->orderBy('id')
                ->get()
                ->map(fn (ThreadCollaborator $collaborator): array => [
                    'id' => (int) $collaborator->id,
                    'name' => $collaborator->user?->name,
                    'email' => $collaborator->user?->defaultEmail?->address,
                    'role' => $collaborator->role,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function referrals(?int $threadId): array
    {
        if ($threadId === null) {
            return [];
        }

        try {
            return ThreadReferral::query()
                ->where('thread_id', $threadId)
                ->orderBy('id')
                ->get()
                ->map(fn (ThreadReferral $referral): array => [
                    'id' => (int) $referral->id,
                    'object_type' => $referral->object_type,
                    'object_id' => (int) $referral->object_id,
                    'created' => $referral->created,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array{canSetStatus: bool, canAssign: bool, canPostNote: bool, canPostReply: bool}
     */
    private function permissions(Ticket $ticket): array
    {
        $staff = Auth::guard('staff')->user();

        if (! $staff instanceof Staff) {
            return [
                'canSetStatus' => false,
                'canAssign' => false,
                'canPostNote' => false,
                'canPostReply' => false,
            ];
        }

        return [
            'canSetStatus' => Gate::forUser($staff)->allows('setStatus', $ticket),
            'canAssign' => Gate::forUser($staff)->allows('assign', $ticket),
            'canPostNote' => Gate::forUser($staff)->allows('postNote', $ticket),
            'canPostReply' => Gate::forUser($staff)->allows('postReply', $ticket),
        ];
    }

    /**
     * @return list<array{id:int,name:string,state:string}>
     */
    private function availableStatuses(): array
    {
        try {
            return TicketStatus::query()
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'name', 'state'])
                ->map(fn (TicketStatus $status): array => [
                    'id' => (int) $status->id,
                    'name' => (string) $status->name,
                    'state' => (string) $status->state,
                ])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }
}
