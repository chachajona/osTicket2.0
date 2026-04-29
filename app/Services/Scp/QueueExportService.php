<?php

namespace App\Services\Scp;

use App\Models\Queue;
use App\Models\QueueExport;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketPriority;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueExportService
{
    private const DEFAULT_FIELDS = ['number', 'created', 'subject', 'from', 'priority', 'assignee'];

    public function __construct(private readonly LegacyQueueCriteriaParser $criteriaParser) {}

    public function stream(Queue $queue, Staff $staff): StreamedResponse
    {
        $fields = $this->fields($queue);

        return response()->streamDownload(function () use ($queue, $staff, $fields): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, array_map(fn (string $field): string => $this->heading($field), $fields));

            $query = Ticket::query()
                ->with(['cdata', 'staff', 'user.defaultEmail', 'status'])
                ->orderBy('ticket_id');

            $unsupported = $this->criteriaParser->apply($query, $queue->config, $staff);

            if ($unsupported !== []) {
                fputcsv($output, ['Unsupported queue criteria: '.implode('; ', $unsupported)]);
                fclose($output);

                return;
            }

            $query->chunkById(1000, function ($tickets) use ($output, $fields): void {
                $priorityNames = $this->priorityNames($tickets->pluck('cdata.priority')->filter()->all());

                foreach ($tickets as $ticket) {
                    fputcsv($output, array_map(
                        fn (string $field): mixed => $this->value($ticket, $field, $priorityNames),
                        $fields,
                    ));
                }

                flush();
            }, 'ticket_id');

            fclose($output);
        }, sprintf('queue-%d.csv', $queue->id), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    private function fields(Queue $queue): array
    {
        try {
            $export = QueueExport::query()
                ->where('queue_id', $queue->id)
                ->orderBy('id')
                ->first();
        } catch (QueryException) {
            return self::DEFAULT_FIELDS;
        }

        if (! $export || ! is_string($export->config)) {
            return self::DEFAULT_FIELDS;
        }

        $decoded = json_decode($export->config, true);
        $fields = is_array($decoded)
            ? ($decoded['fields'] ?? $decoded['columns'] ?? $decoded)
            : [];

        if (! is_array($fields)) {
            return self::DEFAULT_FIELDS;
        }

        $fields = array_values(array_filter(array_map(
            fn (mixed $field): ?string => is_string($field) ? $field : null,
            $fields,
        )));

        return $fields !== [] ? $fields : self::DEFAULT_FIELDS;
    }

    private function heading(string $field): string
    {
        return str($field)->replace(['cdata.', '__'], ['', ' '])->replace('_', ' ')->title()->toString();
    }

    /**
     * @param  array<string, string>  $priorityNames
     */
    private function value(Ticket $ticket, string $field, array $priorityNames): mixed
    {
        return match ($field) {
            'ticket_id', 'id' => $ticket->ticket_id,
            'number' => $ticket->number,
            'created' => $ticket->created,
            'updated' => $ticket->updated,
            'closed' => $ticket->closed,
            'status', 'status__name' => $ticket->status?->name,
            'status__state' => $ticket->status?->state,
            'dept_id' => $ticket->dept_id,
            'staff_id' => $ticket->staff_id,
            'assignee' => $ticket->staff?->displayName(),
            'from', 'requester' => $ticket->user?->name ?: $ticket->user?->defaultEmail?->address,
            'cdata.subject', 'subject' => $ticket->cdata?->subject,
            'cdata.priority', 'priority' => $priorityNames[(string) $ticket->cdata?->priority] ?? $ticket->cdata?->priority,
            default => data_get($ticket, $field),
        };
    }

    /**
     * @param  array<int, mixed>  $priorityIds
     * @return array<string, string>
     */
    private function priorityNames(array $priorityIds): array
    {
        $priorityIds = array_values(array_unique(array_filter($priorityIds, is_numeric(...))));

        if ($priorityIds === []) {
            return [];
        }

        try {
            return TicketPriority::query()
                ->whereIn('priority_id', $priorityIds)
                ->pluck('priority', 'priority_id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }
}
