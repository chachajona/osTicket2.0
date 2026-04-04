<?php

namespace App\Prototype\DynamicForms;

use App\Models\Ticket;
use App\Models\TicketCdata;
use Illuminate\Support\Collection;

/**
 * Approach A: Direct __cdata table access via Eloquent relationship.
 *
 * The ost_ticket__cdata table is a materialized view maintained by osTicket
 * that flattens EAV custom field data into relational columns. Each column
 * name corresponds to a form field "name" (not label).
 *
 * Pros:
 *   - Single JOIN query via eager loading (1+1 queries for collection)
 *   - Column names are indexed; queries very fast
 *   - No additional abstractions needed
 *   - Already implemented in Ticket::cdata() relationship
 *
 * Cons:
 *   - Column names (e.g. shortdesc, callerid) are not human-readable labels
 *   - Requires schema knowledge to map columns to business meaning
 *   - __cdata must be kept in sync by osTicket's signal system
 *   - Limited to fields explicitly materialized in the view
 *
 * Usage:
 *   $result = CdataApproach::getCustomFields($ticketId);
 *   // Returns: ['subject' => '...', 'priority' => '...', ...]
 */
class CdataApproach
{
    /**
     * Fetch custom fields for a single ticket using the cdata relationship.
     *
     * Issues 2 queries total: one for the ticket, one for cdata.
     *
     * @return array<string, mixed>|null Column-name-keyed array, or null if not found
     */
    public static function getCustomFields(int $ticketId): ?array
    {
        $ticket = Ticket::with('cdata')->find($ticketId);

        if (! $ticket || ! $ticket->cdata) {
            return null;
        }

        // Strip the ticket_id key; return only field columns
        return collect($ticket->cdata->getAttributes())
            ->except('ticket_id')
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    /**
     * Fetch a specific field from cdata for a ticket.
     *
     * @param  string  $field  Column name in ticket__cdata (e.g. 'subject', 'priority')
     */
    public static function getField(int $ticketId, string $field): mixed
    {
        $cdata = TicketCdata::where('ticket_id', $ticketId)->first();

        return $cdata?->{$field};
    }

    /**
     * Fetch custom fields for multiple tickets efficiently (eager load).
     *
     * Issues 2 queries regardless of collection size (N+1 avoided).
     *
     * @param  array<int>  $ticketIds
     * @return Collection<int, array<string, mixed>> Keyed by ticket_id
     */
    public static function getCustomFieldsBatch(array $ticketIds): Collection
    {
        return Ticket::with('cdata')
            ->whereIn('ticket_id', $ticketIds)
            ->get()
            ->mapWithKeys(function (Ticket $ticket) {
                $fields = $ticket->cdata
                    ? collect($ticket->cdata->getAttributes())
                        ->except('ticket_id')
                        ->filter(fn ($v) => $v !== null)
                        ->all()
                    : [];

                return [$ticket->ticket_id => $fields];
            });
    }

    /**
     * Return raw TicketCdata model for direct attribute access.
     *
     * Useful when caller already has the Ticket model loaded.
     */
    public static function getCdataModel(Ticket $ticket): ?TicketCdata
    {
        // If cdata relation is already loaded, use it; otherwise lazy load.
        return $ticket->relationLoaded('cdata')
            ? $ticket->cdata
            : $ticket->cdata()->first();
    }

    /**
     * List the known cdata columns (minus the PK).
     * Useful for documentation and field discovery.
     *
     * @return array<string>
     */
    public static function knownColumns(): array
    {
        return [
            'subject',
            'priority',
            'shortdesc',
            'callerid',
            'transid',
            'transdt',
            'ewallet',
            'bankacc',
            'shopaddr',
            'provider',
            'resolution',
        ];
    }
}
